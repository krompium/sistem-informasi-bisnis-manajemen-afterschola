<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'password', 'is_active', 'school_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sekolah yang ditugaskan ke trainer ini (pivot trainer_school).
     */
    public function assignedSchools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'trainer_school', 'user_id', 'school_id')
            ->withTimestamps();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'created_by');
    }

    /**
     * ID sekolah yang boleh diakses trainer ini — dasar isolasi data.
     *
     * @return array<int, int>
     */
    public function assignedSchoolIds(): array
    {
        return $this->assignedSchools()->pluck('schools.id')->all();
    }

    /**
     * Apakah user boleh mengakses data sekolah tertentu.
     * Management/Finance/HR melihat semua; trainer hanya sekolah yang dipegang.
     */
    public function handlesSchool(int $schoolId): bool
    {
        if ($this->hasAnyRole(['management', 'finance', 'hr'])) {
            return true;
        }

        if ($this->hasRole('sekolah')) {
            return $this->school_id === $schoolId;
        }

        return in_array($schoolId, $this->assignedSchoolIds(), true);
    }

    /**
     * Hot issue yang sudah ditutup/dismiss oleh user ini — popup tidak
     * akan menampilkannya lagi setelah ini tercatat.
     */
    public function dismissedHotIssues(): BelongsToMany
    {
        return $this->belongsToMany(HotIssue::class)
            ->withPivot('dismissed_at')
            ->withTimestamps();
    }
}