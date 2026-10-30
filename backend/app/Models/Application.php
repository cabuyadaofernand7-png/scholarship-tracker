<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];
    protected $casts = [
        'assigned_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function scholarship() { return $this->belongsTo(Scholarship::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'assigned_reviewer_id'); }
    public function submissions() { return $this->hasMany(Submission::class); }
    public function notifications() { return $this->hasMany(AppNotification::class); }
}
