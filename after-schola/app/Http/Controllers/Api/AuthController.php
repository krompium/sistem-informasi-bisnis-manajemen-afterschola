<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login: email + password -> token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun dinonaktifkan. Hubungi Management.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('assignedSchools', 'branches')),
        ]);
    }

    /**
     * Data user yang sedang login (untuk routing dashboard per role).
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('assignedSchools', 'branches'));
    }

    /**
     * Logout: cabut token yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * Kirim link reset password (mailer=log di dev).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Respons sama untuk email terdaftar maupun tidak, agar akun tidak bisa ditebak dari luar.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Jika email terdaftar, tautan untuk mengatur ulang password sudah dikirim. Cek juga folder spam.',
        ]);
    }

    /**
     * Reset password dengan token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->setRememberToken(Str::random(60));
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => ['Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.'],
            ]);
        }

        return response()->json(['message' => 'Password berhasil diubah. Silakan masuk dengan password baru.']);
    }
}
