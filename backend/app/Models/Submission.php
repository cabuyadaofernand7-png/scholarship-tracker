<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $guarded = [];

    public function application() { return $this->belongsTo(Application::class); }
    public function requirement() { return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id'); }
}
