<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\FunctionPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SaveUserRequest extends FormRequest
{
    // Otorisasi sebelum validasi: user di luar scope harus 404, bukan 422.
    public function authorize(): bool
    {
        $target = $this->route('user');
        $target ? Gate::authorize('update', $target) : Gate::authorize('create', User::class);

        return true;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $target = $this->route('user');
        $required = $target ? 'sometimes' : 'required';
        // Staf cabang tidak boleh membuat akun pusat atau menaruh user di cabang di luar miliknya.
        $scopes = $actor->hasAllBranches() ? ['all', 'assigned'] : ['assigned'];
        $assigned = $this->input('branch_scope', $target?->branch_scope) === 'assigned';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', Rule::unique('users', 'email')->ignore($target)],
            'password' => $target ? ['sometimes', 'nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            // Orangtua dibuat lewat form trial (Fase 2), bukan lewat manajemen user internal.
            'role' => [$required, Rule::in(Role::where('name', '!=', 'parent')->pluck('name')->all())],
            'is_active' => ['sometimes', 'boolean'],
            'branch_scope' => [$required, Rule::in($scopes)],
            // Scope 'assigned' wajib punya cabang; scope 'all' mengabaikan branch_ids.
            'branch_ids' => $assigned
                ? [($target && ! $this->has('branch_scope')) ? 'sometimes' : 'required', 'array', 'min:1']
                : ['sometimes', 'array'],
            'branch_ids.*' => ['integer', Rule::in($actor->accessibleBranchIds())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(FunctionPermissions::ALL)],
            'school_ids' => ['sometimes', 'array'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
            'subjects_taught' => ['sometimes', 'array'],
            'subjects_taught.*' => ['string', 'max:255'],
            'levels_taught' => ['sometimes', 'array'],
            'levels_taught.*' => [Rule::in(['beginner', 'intermediate'])],
        ];
    }
}
