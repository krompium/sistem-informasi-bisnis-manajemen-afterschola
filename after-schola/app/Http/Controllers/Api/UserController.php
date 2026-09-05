<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Kelola user & role — hanya Management (permission: manage users).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with('assignedSchools');

        if ($request->filled('role')) {
            $query->role($request->string('role')->value());
        }

        return UserResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(Role::pluck('name')->all())],
            'is_active' => ['sometimes', 'boolean'],
            'school_ids' => ['sometimes', 'array'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->assignRole($data['role']);

        if (! empty($data['school_ids']) && $data['role'] === 'trainer') {
            $user->assignedSchools()->sync($data['school_ids']);
        }

        return (new UserResource($user->load('assignedSchools')))->response()->setStatusCode(201);
    }

    public function show(User $user)
    {
        return new UserResource($user->load('assignedSchools'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(Role::pluck('name')->all())],
            'is_active' => ['sometimes', 'boolean'],
            'school_ids' => ['sometimes', 'array'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ]);

        $user->fill(collect($data)->only(['name', 'email', 'is_active'])->all());
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles($data['role']);
        }
        if (array_key_exists('school_ids', $data)) {
            $user->assignedSchools()->sync($data['school_ids'] ?? []);
        }

        return new UserResource($user->load('assignedSchools'));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User dihapus.']);
    }
}
