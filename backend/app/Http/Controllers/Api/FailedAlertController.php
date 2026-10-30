<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FailedAlert;

class FailedAlertController extends Controller
{
    public function index()
    {
        $items = FailedAlert::with('notification')
            ->whereNull('resolved_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'notification_id' => $f->app_notification_id,
                'type' => $f->notification->type,
                'channel' => $f->notification->channel,
                'application_id' => $f->notification->application_id,
                'recipient_id' => $f->notification->recipient_id,
                'attempts' => $f->notification->attempts,
                'reason' => $f->reason,
                'created_at' => $f->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function resolve(FailedAlert $failedAlert)
    {
        if ($failedAlert->resolved_at) {
            return response()->json(['message' => 'Already resolved.'], 409);
        }

        $failedAlert->update(['resolved_at' => now()]);

        return response()->json(['data' => [
            'id' => $failedAlert->id,
            'resolved_at' => $failedAlert->resolved_at->toIso8601String(),
        ]]);
    }
}
