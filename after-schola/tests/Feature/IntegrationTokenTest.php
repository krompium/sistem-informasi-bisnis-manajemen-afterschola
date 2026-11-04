<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * SYS-05 / PRD 10.3: token integrasi untuk akun layanan (role Keuangan), dibuat dan dicabut oleh pemegang "manage integrations".
 */
class IntegrationTokenTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    private User $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->service = $this->makeUser('finance');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['user_id' => $this->service->id, 'name' => 'Sistem Keuangan', 'abilities' => ['finance:read']], $overrides);
    }

    public function test_only_integration_managers_can_use_the_endpoints(): void
    {
        $this->getJson('/api/integration-tokens')->assertUnauthorized();

        foreach (['finance', 'trainer', 'parent'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/integration-tokens')->assertForbidden();
            $this->postJson('/api/integration-tokens', $this->payload())->assertForbidden();
            $this->deleteJson('/api/integration-tokens/1')->assertForbidden();
        }
    }

    public function test_token_can_carry_both_finance_and_attendance_read_abilities(): void
    {
        Sanctum::actingAs($this->makeUser('management'));

        $this->postJson('/api/integration-tokens', $this->payload(['abilities' => ['finance:read', 'attendance:read']]))->assertCreated()
            ->assertJsonPath('data.abilities', ['finance:read', 'attendance:read']);
        $this->postJson('/api/integration-tokens', $this->payload(['abilities' => ['attendance:write']]))
            ->assertUnprocessable()->assertJsonValidationErrors('abilities.0');
    }

    public function test_creates_token_for_a_finance_service_account_and_shows_it_once(): void
    {
        Sanctum::actingAs($this->makeUser('management'));

        $res = $this->postJson('/api/integration-tokens', $this->payload())->assertCreated()
            ->assertJsonPath('data.name', 'Sistem Keuangan')
            ->assertJsonPath('data.abilities', ['finance:read'])
            ->assertJsonPath('data.user.id', $this->service->id);

        $plain = $res->json('token');
        $this->assertNotEmpty($plain);
        $stored = PersonalAccessToken::findToken($plain);
        $this->assertSame($this->service->id, $stored->tokenable_id);
        $this->assertSame(['finance:read'], $stored->abilities);

        $this->getJson('/api/integration-tokens')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sistem Keuangan')
            ->assertJsonMissingPath('data.0.token');
        $this->assertStringNotContainsString($plain, $this->getJson('/api/integration-tokens')->getContent());
    }

    public function test_validation(): void
    {
        $inactive = $this->makeUser('finance');
        $inactive->update(['is_active' => false]);
        Sanctum::actingAs($this->makeUser('management'));

        $this->postJson('/api/integration-tokens', $this->payload(['user_id' => $this->makeUser('trainer')->id]))->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->postJson('/api/integration-tokens', $this->payload(['user_id' => $inactive->id]))->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->postJson('/api/integration-tokens', $this->payload(['abilities' => ['*']]))->assertStatus(422)->assertJsonValidationErrors('abilities.0');
        $this->postJson('/api/integration-tokens', $this->payload(['abilities' => []]))->assertStatus(422)->assertJsonValidationErrors('abilities');
        $this->postJson('/api/integration-tokens', $this->payload(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_list_excludes_login_tokens_and_offers_service_accounts(): void
    {
        $manager = $this->makeUser('management');
        $manager->createToken('web-spa'); // token login biasa
        $this->service->createToken('integration:Lama', ['finance:read']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/integration-tokens')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lama')
            ->assertJsonPath('service_accounts.0.id', $this->service->id);
    }

    public function test_revoke_removes_only_integration_tokens(): void
    {
        $manager = $this->makeUser('management');
        $web = $manager->createToken('web-spa')->accessToken;
        $integration = $this->service->createToken('integration:Lama', ['finance:read'])->accessToken;
        Sanctum::actingAs($manager);

        $this->deleteJson("/api/integration-tokens/{$web->id}")->assertNotFound();
        $this->deleteJson("/api/integration-tokens/{$integration->id}")->assertNoContent();
        $this->assertNull(PersonalAccessToken::find($integration->id));
        $this->assertNotNull(PersonalAccessToken::find($web->id));
    }
}
