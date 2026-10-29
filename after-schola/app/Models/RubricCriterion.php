<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $fillable = ['name', 'max_score'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
