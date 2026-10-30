<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Applicant;
use App\Services\AlertSender;

class NotificationController extends Controller
{
    public function index(Applicant $applicant)
    {
        $items = AppNotification::where('recipient_id', $applicant->user_id)
            ->where('channel', 'in_app')
            ->where('status', 'sent')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'message' => AlertSender::messageFor($n->type),
                'sent_at' => $n->sent_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }
}
