<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\AlertDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    private const DECIDED = ['approved', 'rejected', 'waitlisted'];

    // A2: staff assign a reviewer; the reviewer is alerted.
    public function assignReviewer(Request $request, Application $application, AlertDispatcher $dispatcher)
    {
        $data = $request->validate([
            'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'reviewer')],
        ]);

        if (in_array($application->status, self::DECIDED, true)) {
            return response()->json(['message' => 'Application already has a final decision.'], 409);
        }

        $application->update([
            'assigned_reviewer_id' => $data['reviewer_id'],
            'assigned_at' => now(),
            'status' => $application->status === 'submitted' ? 'under_review' : $application->status,
            'last_activity_at' => now(),
        ]);

        $dispatcher->newRun();
        $alerts = [];
        foreach (['in_app', 'email'] as $channel) {
            $alerts[$channel] = $dispatcher->dispatch(
                'A2',
                "assigned:app{$application->id}:rev{$data['reviewer_id']}:{$channel}",
                (int) $data['reviewer_id'],
                $application->id,
                $channel,
                'reviewer_assigned'
            );
        }

        return response()->json(['data' => [
            'application_id' => $application->id,
            'assigned_reviewer_id' => (int) $data['reviewer_id'],
            'status' => $application->status,
            'alerts' => $alerts,
        ]]);
    }

    // A4: status change; the applicant gets a GENERIC notification only.
    public function updateStatus(Request $request, Application $application, AlertDispatcher $dispatcher)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'needs_correction', 'approved', 'rejected', 'waitlisted'])],
        ]);

        if (in_array($application->status, self::DECIDED, true)) {
            return response()->json(['message' => 'Application already has a final decision.'], 409);
        }
        if ($application->status === $data['status']) {
            return response()->json(['message' => 'Status unchanged.'], 409);
        }

        $application->update(['status' => $data['status'], 'last_activity_at' => now()]);

        $dispatcher->newRun();
        $alerts = [];
        foreach (['in_app', 'email'] as $channel) {
            $alerts[$channel] = $dispatcher->dispatch(
                'A4',
                'status:app' . $application->id . ':' . Str::uuid() . ":{$channel}",
                $application->applicant->user_id,
                $application->id,
                $channel,
                'status_updated'
            );
        }

        return response()->json(['data' => [
            'application_id' => $application->id,
            'status' => $application->status,
            'alerts' => $alerts,
        ]]);
    }
}
