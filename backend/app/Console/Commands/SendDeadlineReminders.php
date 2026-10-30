<?php

namespace App\Console\Commands;

use App\Exceptions\PermanentDeliveryException;
use App\Exceptions\TransientDeliveryException;
use App\Models\AppNotification;
use App\Models\Application;
use App\Models\FailedAlert;
use App\Models\Scholarship;
use App\Services\AlertSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendDeadlineReminders extends Command
{
    protected $signature = 'alerts:send-reminders';
    protected $description = 'A1: bounded, idempotent 7/3/1-day deadline reminders with retry and failure routing';

    private AlertSender $sender;
    private string $runId;
    private array $counts = [];

    public function handle(AlertSender $sender): int
    {
        $this->sender = $sender;
        $this->runId = (string) Str::uuid();
        $this->counts = ['sent' => 0, 'recovered' => 0, 'failed_permanent' => 0, 'skipped_duplicate' => 0];
        $this->log('run_started', []);

        foreach (config('alerts.reminder_offsets') as $offset) {
            $target = now()->startOfDay()->addDays($offset)->toDateString();

            Scholarship::where('status', 'open')
                ->whereDate('deadline_date', $target)
                ->each(function (Scholarship $scholarship) use ($offset) {
                    Application::with('applicant.user')
                        ->where('scholarship_id', $scholarship->id)
                        ->whereNotIn('status', ['approved', 'rejected', 'waitlisted'])
                        ->whereHas('submissions', fn ($q) => $q->whereIn('status', ['pending', 'rejected']))
                        ->chunkById(100, function ($applications) use ($offset) {
                            foreach ($applications as $application) {
                                foreach (['in_app', 'email'] as $channel) {
                                    $this->process($application, $offset, $channel);
                                }
                            }
                        });
                });
        }

        $this->log('run_finished', $this->counts);
        $this->info('Done: ' . json_encode($this->counts));

        return self::SUCCESS;
    }

    private function process(Application $application, int $offset, string $channel): void
    {
        $key = "reminder:{$offset}:app{$application->id}:{$channel}";

        $notification = AppNotification::firstOrCreate(
            ['dedupe_key' => $key],
            [
                'recipient_id' => $application->applicant->user_id,
                'application_id' => $application->id,
                'channel' => $channel,
                'type' => "reminder_{$offset}",
                'status' => 'pending',
                'attempts' => 0,
            ]
        );

        if ($notification->status !== 'pending') {
            $this->counts['skipped_duplicate']++;
            $this->logOutcome($notification, $notification->attempts, 'skipped_duplicate');
            return;
        }

        $this->deliver($notification, $application);
    }

    private function deliver(AppNotification $notification, Application $application): void
    {
        $max = (int) config('alerts.max_attempts');
        $delays = config('alerts.backoff_seconds');

        for ($attempt = $notification->attempts + 1; $attempt <= $max; $attempt++) {
            try {
                $this->sender->send($notification, $application, $attempt);

                $notification->update([
                    'attempts' => $attempt,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                ]);
                $outcome = $attempt > 1 ? 'recovered' : 'sent';
                $this->counts[$outcome]++;
                $this->logOutcome($notification, $attempt, $outcome);
                return;
            } catch (PermanentDeliveryException $e) {
                $this->failPermanently($notification, $attempt, $e->getMessage());
                return;
            } catch (TransientDeliveryException $e) {
                $notification->update(['attempts' => $attempt, 'last_error' => $e->getMessage()]);

                if ($attempt >= $max) {
                    $this->failPermanently($notification, $attempt, 'RETRIES_EXHAUSTED');
                    return;
                }

                $this->logOutcome($notification, $attempt, 'retry', $e->getMessage());
                sleep((int) ($delays[$attempt - 1] ?? end($delays)));
            }
        }

        $this->failPermanently($notification, $notification->attempts, 'RETRIES_EXHAUSTED');
    }

    private function failPermanently(AppNotification $notification, int $attempt, string $reason): void
    {
        $notification->update(['attempts' => $attempt, 'status' => 'failed_permanent', 'last_error' => $reason]);
        FailedAlert::firstOrCreate(['app_notification_id' => $notification->id], ['reason' => $reason]);

        $this->counts['failed_permanent']++;
        $this->logOutcome($notification, $attempt, 'failed_permanent', $reason);
    }

    private function logOutcome(AppNotification $n, int $attempt, string $outcome, ?string $errorCode = null): void
    {
        $this->log('alert_delivery', [
            'automation' => 'A1',
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

    private function log(string $event, array $context): void
    {
        Log::channel('alerts')->info($event, ['correlation_id' => $this->runId] + $context);
    }
}
