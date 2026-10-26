<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'education_level',
        'origin_school',
        'parent_email',
        'program_id',
        'program_trial_schedule_id',
        'status',
    ];
}
