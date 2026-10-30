<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scholarship extends Model
{
    protected $guarded = [];
    protected $casts = ['deadline_date' => 'date'];

    public function requirements() { return $this->hasMany(DocumentRequirement::class); }
    public function applications() { return $this->hasMany(Application::class); }
}
