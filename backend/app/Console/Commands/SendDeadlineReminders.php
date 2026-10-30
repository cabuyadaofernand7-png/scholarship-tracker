<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Scholarship;
use App\Services\AlertDispatcher;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'alerts:send-reminders';
    protected $description = 'A1: bounded, idempotent 7/3/1-day deadline reminders';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $dispatcher->newRun();
        $counts = ['sent' => 0, 'recovered' => 0, 'failed_permanent' => 0, 'skipped_duplicate' => 0];
        $dispatcher->logEvent('run_started', ['automation' => 'A1']);

        foreach (config('alerts.reminder_offsets') as $offset) {
            $target = now()->startOfDay()->addDays($offset)->toDateString();

            Scholarship::where('status', 'open')
                ->whereDate('deadline_date', $target)
                ->each(function (Scholarship $scholarship) use ($offset, $dispatcher, &$counts) {
                    Application::with('applicant')
                        ->where('scholarship_id', $scholarship->id)
                        ->whereNotIn('status', ['approved', 'rejected', 'waitlisted'])
                        ->whereHas('submissions', fn ($q) => $q->whereIn('status', ['pending', 'rejected']))
                        ->chunkById(100, function ($applications) use ($offset, $dispatcher, &$counts) {
                            foreach ($applications as $application) {
                                foreach (['in_app', 'email'] as $channel) {
                                    $outcome = $dispatcher->dispatch(
                                        'A1',
                                        "reminder:{$offset}:app{$application->id}:{$channel}",
                                        $application->applicant->user_id,
                                        $application->id,
                                        $channel,
                                        "reminder_{$offset}"
                                    );
                                    $counts[$outcome]++;
                                }
                            }
                        });
                });
        }

        $dispatcher->logEvent('run_finished', ['automation' => 'A1'] + $counts);
        $this->info('Done: ' . json_encode($counts));

        return self::SUCCESS;
    }
}
