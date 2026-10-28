<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Kelola user internal (permission: manage users), dibatasi scope cabang pelaku.
 * Tidak ada DELETE: user dinonaktifkan lewat is_active (MGT-04).
 */
class UserController extends Controller
{
    private const WITH = ['roles.permissions', 'permissions', 'branches', 'assignedSchools'];

    public function index(Request $request)
    {
        $query = User::visibleTo($request->user())->with(self::WITH);

        if ($request->filled('role')) {
            $query->role($request->string('role')->value());
        }

        // TODO(nanti): paginasi — saat ini utuh karena dipakai dropdown (mis. penugasan trainer).
        return UserResource::collection($query->orderBy('name')->get());
    }

    public function store(SaveUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'branch_scope' => $data['branch_scope'],
            'subjects_taught' => $data['subjects_taught'] ?? [],
            'levels_taught' => $data['levels_taught'] ?? [],
        ]);
        $user->assignRole($data['role']);
        $user->syncPermissions($data['permissions'] ?? []);
        $this->syncBranches($user, $request->user(), $data);

        if (! empty($data['school_ids']) && $data['role'] === 'trainer') {
            $user->assignedSchools()->sync($data['school_ids']);
        }

        return (new UserResource($user->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(self::WITH));
    }

    public function update(SaveUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $user->is($request->user())) {
            throw ValidationException::withMessages(['is_active' => ['Tidak bisa menonaktifkan akun sendiri.']]);
        }

        $user->fill(collect($data)->only(['name', 'email', 'is_active', 'branch_scope', 'subjects_taught', 'levels_taught'])->all());
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles($data['role']);
        }
        if (array_key_exists('permissions', $data)) {
            $user->syncPermissions($data['permissions']);
        }
        if (array_key_exists('school_ids', $data)) {
            $user->assignedSchools()->sync($data['school_ids'] ?? []);
        }
        $this->syncBranches($user, $request->user(), $data);

        // Token yang sudah terbit tetap berlaku sampai dicabut; akun nonaktif harus langsung putus.
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->delete();
        }

        return new UserResource($user->load(self::WITH));
    }

    /**
     * Cabang di luar jangkauan pelaku tidak ikut berubah (staf cabang hanya "melihat" cabangnya).
     */
    private function syncBranches(User $user, User $actor, array $data): void
    {
        if (($data['branch_scope'] ?? $user->branch_scope) === 'all') {
            $requested = [];
        } elseif (array_key_exists('branch_ids', $data)) {
            $requested = $data['branch_ids'];
        } else {
            return;
        }

        $outside = $user->branches()->pluck('branches.id')->diff($actor->accessibleBranchIds());
        $user->branches()->sync($outside->merge($requested)->unique()->values()->all());
    }
}
