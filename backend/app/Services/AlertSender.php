<?php

namespace App\Services;

use App\Exceptions\PermanentDeliveryException;
use App\Exceptions\TransientDeliveryException;
use App\Models\AppNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AlertSender
{
    public function send(AppNotification $n, int $attempt): void
    {
        // The in-app notification is the database row itself.
        if ($n->channel === 'in_app') {
            return;
        }

        $email = $n->recipient->email;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new PermanentDeliveryException('INVALID_RECIPIENT_ADDRESS');
        }

        // Demo-only fault injection, disclosed in the docs and visible in the logs.
        $simulated = config('alerts.simulate_transient_failure_email');
        if ($simulated !== '' && $attempt === 1 && $email === $simulated) {
            throw new TransientDeliveryException('SIMULATED_MAIL_TIMEOUT');
        }

        Mail::raw(self::messageFor($n->type), function ($message) use ($email) {
            $message->to($email)->subject('Scholarship notification');
        });
    }

    // Generic text only: no names, no decision information.
    public static function messageFor(string $type): string
    {
        if (str_starts_with($type, 'reminder_')) {
            $days = (int) Str::after($type, 'reminder_');

            return "Reminder: your scholarship application deadline is in {$days} day(s). Please log in to review your required documents.";
        }

        return match ($type) {
            'reviewer_assigned' => 'An application has been assigned to you for review. Please log in to view it.',
            'stale_application' => 'An application has been waiting longer than the expected time and needs attention. Please log in to review it.',
            'status_updated' => 'Your application status has been updated. Please log in to view it.',
            default => 'You have a new notification. Please log in to view it.',
        };
    }
}
