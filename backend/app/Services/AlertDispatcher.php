<?php

namespace App\Services;

use App\Exceptions\PermanentDeliveryException;
use App\Exceptions\TransientDeliveryException;
use App\Models\AppNotification;
use App\Models\FailedAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AlertDispatcher
{
    private string $runId;

    public function __construct(private AlertSender $sender)
    {
        $this->runId = (string) Str::uuid();
    }

    public function newRun(): string
    {
        return $this->runId = (string) Str::uuid();
    }

    public function logEvent(string $event, array $context = []): void
    {
        Log::channel('alerts')->info($event, ['correlation_id' => $this->runId] + $context);
    }

    /** Returns: sent | recovered | failed_permanent | skipped_duplicate */
    public function dispatch(
        string $automation,
        string $key,
        int $recipientId,
        ?int $applicationId,
        string $channel,
        string $type
    ): string {
        $n = AppNotification::firstOrCreate(
            ['dedupe_key' => $key],
            [
                'recipient_id' => $recipientId,
                'application_id' => $applicationId,
                'channel' => $channel,
                'type' => $type,
                'status' => 'pending',
                'attempts' => 0,
            ]
        );

        if ($n->status !== 'pending') {
            $this->logOutcome($automation, $n, $n->attempts, 'skipped_duplicate');

            return 'skipped_duplicate';
        }

        return $this->deliver($automation, $n);
    }

    private function deliver(string $automation, AppNotification $n): string
    {
        $max = (int) config('alerts.max_attempts');
        $delays = config('alerts.backoff_seconds');

        for ($attempt = $n->attempts + 1; $attempt <= $max; $attempt++) {
            try {
                $this->sender->send($n, $attempt);

                $n->update([
                    'attempts' => $attempt,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                ]);
                $outcome = $attempt > 1 ? 'recovered' : 'sent';
                $this->logOutcome($automation, $n, $attempt, $outcome);

                return $outcome;
            } catch (PermanentDeliveryException $e) {
                return $this->failPermanently($automation, $n, $attempt, $e->getMessage());
            } catch (TransientDeliveryException $e) {
                $n->update(['attempts' => $attempt, 'last_error' => $e->getMessage()]);

                if ($attempt >= $max) {
                    return $this->failPermanently($automation, $n, $attempt, 'RETRIES_EXHAUSTED');
                }

                $this->logOutcome($automation, $n, $attempt, 'retry', $e->getMessage());
                sleep((int) ($delays[$attempt - 1] ?? end($delays)));
            }
        }

        return $this->failPermanently($automation, $n, $n->attempts, 'RETRIES_EXHAUSTED');
    }

    private function failPermanently(string $automation, AppNotification $n, int $attempt, string $reason): string
    {
        $n->update(['attempts' => $attempt, 'status' => 'failed_permanent', 'last_error' => $reason]);
        FailedAlert::firstOrCreate(['app_notification_id' => $n->id], ['reason' => $reason]);
        $this->logOutcome($automation, $n, $attempt, 'failed_permanent', $reason);

        return 'failed_permanent';
    }

    private function logOutcome(string $automation, AppNotification $n, int $attempt, string $outcome, ?string $errorCode = null): void
    {
        $this->logEvent('alert_delivery', [
            'automation' => $automation,
            'notification_id' => $n->id,
            'application_id' => $n->application_id,
            'recipient_id' => $n->recipient_id,
            'channel' => $n->channel,
            'type' => $n->type,
            'attempt' => $attempt,
            'outcome' => $outcome,
            'error_code' => $errorCode,
        ]);
    }
}
