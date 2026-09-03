<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['name', 'address', 'pic_name', 'pic_phone'];

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trainer_school', 'school_id', 'user_id')
            ->withTimestamps();
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function expoReports(): HasMany
    {
        return $this->hasMany(ExpoReport::class);
    }
}
