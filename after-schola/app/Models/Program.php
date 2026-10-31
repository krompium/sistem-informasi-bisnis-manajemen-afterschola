<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'jenjang',
        'tipe',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function levels()
    {
        return $this->hasMany(ProgramLevel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rubricCriteria()
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('id');
    }

    /** MGT-13: program yang sudah dipakai tidak boleh dihapus, cukup dinonaktifkan. */
    public function isInUse(): bool
    {
        return Lead::where('program_id', $this->id)->exists()
            || TrialEvaluation::where('program_id', $this->id)->exists()
            || Registration::where('program_id', $this->id)->exists()
            || TrialSlot::where('program_id', $this->id)->exists();
    }
}
