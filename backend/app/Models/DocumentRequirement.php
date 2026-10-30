<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequirement extends Model
{
    protected $guarded = [];

    public function scholarship() { return $this->belongsTo(Scholarship::class); }

    // A blank (null) field means "applies to everyone"
    public function appliesTo(Applicant $applicant): bool
    {
        return ($this->year_level === null || $this->year_level == $applicant->year_level)
            && ($this->program === null || $this->program === $applicant->program)
            && ($this->applicant_type === null || $this->applicant_type === $applicant->applicant_type);
    }
}
