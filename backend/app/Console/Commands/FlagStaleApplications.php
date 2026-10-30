<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\User;
use App\Services\AlertDispatcher;
use Illuminate\Console\Command;

class FlagStaleApplications extends Command
{
    protected $signature = 'alerts:flag-stale {--days=}';
    protected $description = 'A3: alert staff about applications waiting too long for assignment or review';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('alerts.stale_days');
        $cutoff = now()->subDays($days);
        $staff = User::where('role', 'staff')->get();
        $counts = ['sent' => 0, 'recovered' => 0, 'failed_permanent' => 0, 'skipped_duplicate' => 0];

        $dispatcher->newRun();
        $dispatcher->logEvent('run_started', ['automation' => 'A3', 'stale_days' => $days]);

        Application::whereIn('status', ['submitted', 'under_review'])
            ->where(function ($q) use ($cutoff) {
                $q->where(fn ($u) => $u->whereNull('assigned_reviewer_id')->where('created_at', '<=', $cutoff))
                  ->orWhere(fn ($r) => $r->whereNotNull('assigned_reviewer_id')->where('assigned_at', '<=', $cutoff));
            })
            ->chunkById(100, function ($applications) use ($staff, $dispatcher, &$counts) {
                foreach ($applications as $application) {
                    foreach ($staff as $member) {
                        foreach (['in_app', 'email'] as $channel) {
                            $outcome = $dispatcher->dispatch(
                                'A3',
                                "stale:app{$application->id}:staff{$member->id}:{$channel}",
                                $member->id,
                                $application->id,
                                $channel,
                                'stale_application'
                            );
                            $counts[$outcome]++;
                        }
                    }
                }
            });

        $dispatcher->logEvent('run_finished', ['automation' => 'A3'] + $counts);
        $this->info('Done: ' . json_encode($counts));

        return self::SUCCESS;
    }
}
