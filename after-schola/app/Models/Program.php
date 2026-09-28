<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'tipe',
        'deskripsi',
        'biaya',
        'status_aktif',
    ];

    public function jadwalTrial()
    {
        return $this->hasMany(ProgramTrialSchedule::class);
    }
}