<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramTrialSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}