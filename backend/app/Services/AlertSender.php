<?php

namespace App\Services;

use App\Exceptions\PermanentDeliveryException;
use App\Exceptions\TransientDeliveryException;
use App\Models\AppNotification;
use App\Models\Application;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AlertSender
{
    public function send(AppNotification $n, Application $application, int $attempt): void
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

        Mail::raw($this->body($n), function ($message) use ($email) {
            $message->to($email)->subject('Scholarship reminder');
        });
    }

    // Generic text only: no names, no decision information.
    private function body(AppNotification $n): string
    {
        $days = (int) Str::after($n->type, 'reminder_');

        return "This is a reminder that your scholarship application deadline is in {$days} day(s). "
             . 'Please log in to review your required documents.';
    }
}
