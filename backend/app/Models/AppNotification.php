<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $guarded = [];
    protected $casts = ['sent_at' => 'datetime'];

    public function recipient() { return $this->belongsTo(User::class, 'recipient_id'); }
    public function application() { return $this->belongsTo(Application::class); }
    public function failedAlerts() { return $this->hasMany(FailedAlert::class); }
}
