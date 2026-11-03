<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntegrationTokenRequest;
use App\Http\Resources\IntegrationTokenResource;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * SYS-05 / PRD 10.3: token integrasi untuk akun layanan Keuangan. Permission "manage integrations" dicek di route.
 * Token integrasi = nama berawalan "integration:" dan tanpa ability "*" (token login tidak pernah tampil atau bisa dicabut dari sini).
 */
class IntegrationTokenController extends Controller
{
    private const PREFIX = 'integration:';

    public function index()
    {
        return IntegrationTokenResource::collection($this->integrationTokens()->with('tokenable:id,name,email')->latest('id')->get())
            ->additional(['service_accounts' => User::role('finance')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])]);
    }

    public function store(StoreIntegrationTokenRequest $request)
    {
        $data = $request->validated();
        $token = User::findOrFail($data['user_id'])->createToken(self::PREFIX.$data['name'], $data['abilities']);

        // Nilai token hanya muncul di respons ini; yang tersimpan hanya hash-nya.
        return (new IntegrationTokenResource($token->accessToken->load('tokenable:id,name,email')))
            ->additional(['token' => $token->plainTextToken])
            ->response()->setStatusCode(201);
    }

    public function destroy(string $id)
    {
        $this->integrationTokens()->findOrFail($id)->delete();

        return response()->noContent();
    }

    private function integrationTokens()
    {
        return PersonalAccessToken::where('name', 'like', self::PREFIX.'%')->where('abilities', 'not like', '%"*"%');
    }
}
