<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedAlert extends Model
{
    protected $guarded = [];
    protected $casts = ['resolved_at' => 'datetime'];

    public function notification() { return $this->belongsTo(AppNotification::class, 'app_notification_id'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
