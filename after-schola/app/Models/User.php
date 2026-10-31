<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'is_active', 'branch_scope', 'subjects_taught', 'levels_taught'])]
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
            'subjects_taught' => 'array',
            'levels_taught' => 'array',
        ];
    }

    /** Tautan halaman atur password di SPA; dipakai email reset bawaan dan email pendaftaran trial. */
    public function passwordResetUrl(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $this->email]);
    }

    /**
     * Sekolah yang ditugaskan ke trainer ini (pivot trainer_school).
     */
    public function assignedSchools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'trainer_school', 'user_id', 'school_id')
            ->withTimestamps();
    }

    public function createdStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'created_by');
    }
	
	public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_in_team')
            ->withTimestamps();
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

        return in_array($schoolId, $this->assignedSchoolIds(), true);
    }

    /**
     * Cabang yang ditugaskan ke user ini (pivot branch_user; bermakna untuk scope 'assigned').
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function hasAllBranches(): bool
    {
        return $this->branch_scope === 'all';
    }

    /**
     * ID cabang yang boleh diakses user — dasar isolasi data per cabang.
     *
     * @return array<int, int>
     */
    public function accessibleBranchIds(): array
    {
        return $this->hasAllBranches()
            ? Branch::pluck('id')->all()
            : $this->branches()->pluck('branches.id')->all();
    }

    public function handlesBranch(int $branchId): bool
    {
        return $this->hasAllBranches() || in_array($branchId, $this->accessibleBranchIds(), true);
    }

    /**
     * User internal yang boleh dikelola/dilihat `$viewer` (orang tua tidak pernah termasuk).
     * Scope 'all' melihat semua; scope 'assigned' hanya dirinya dan user ber-scope 'assigned'
     * yang berbagi minimal satu cabang (staf cabang tidak boleh menyentuh akun pusat).
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $query->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'parent'));

        if ($viewer->hasAllBranches()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereKey($viewer->id)->orWhere(
            fn ($q) => $q->where('branch_scope', 'assigned')
                ->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $viewer->accessibleBranchIds())),
        ));
    }
}
