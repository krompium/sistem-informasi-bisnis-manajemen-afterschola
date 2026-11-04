<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Token integrasi untuk akun layanan (role Keuangan, aktif). Hak akses ("manage integrations") dicek di route. */
class StoreIntegrationTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! User::role('finance')->where('is_active', true)->whereKey($value)->exists()) {
                    $fail('Akun layanan harus berupa user Keuangan yang aktif.');
                }
            }],
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['in:finance:read,attendance:read'],
        ];
    }
}
