<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 1, slice 3: pengaturan pembayaran global (MGT-14, KEU-09, BR-08).
 */
class PaymentSettingTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'due_days' => 14,
            'reminder_interval_days' => 2,
            'payment_instructions' => 'Transfer lalu unggah bukti bayar.',
            'bank_account' => 'BCA 1234567890 a.n. AfterSchola',
        ], $overrides);
    }

    public function test_guest_gets_401_and_roles_without_permission_get_403(): void
    {
        $this->getJson('/api/payment-settings')->assertStatus(401);

        foreach (['trainer', 'hr'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/payment-settings')->assertStatus(403);
            $this->putJson('/api/payment-settings', $this->validPayload())->assertStatus(403);
        }
    }

    public function test_show_returns_defaults_when_nothing_is_saved(): void
    {
        Sanctum::actingAs($this->makeUser('management'));

        $this->getJson('/api/payment-settings')
            ->assertOk()
            ->assertJsonPath('data.due_days', 7)
            ->assertJsonPath('data.reminder_interval_days', 3)
            ->assertJsonPath('data.payment_instructions', null)
            ->assertJsonPath('data.bank_account', null);
    }

    public function test_management_and_finance_can_update_and_there_is_always_one_row(): void
    {
        Sanctum::actingAs($this->makeUser('management'));
        $this->putJson('/api/payment-settings', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.due_days', 14);

        Sanctum::actingAs($this->makeUser('finance'));
        $this->putJson('/api/payment-settings', $this->validPayload(['due_days' => 10]))->assertOk();
        $this->getJson('/api/payment-settings')
            ->assertOk()
            ->assertJsonPath('data.due_days', 10)
            ->assertJsonPath('data.bank_account', 'BCA 1234567890 a.n. AfterSchola');

        $this->assertDatabaseCount('payment_settings', 1);
    }

    public function test_update_validates_input(): void
    {
        Sanctum::actingAs($this->makeUser('management'));

        $this->putJson('/api/payment-settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_days', 'reminder_interval_days']);
        $this->putJson('/api/payment-settings', $this->validPayload(['due_days' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('due_days');
        $this->putJson('/api/payment-settings', $this->validPayload(['due_days' => 91]))
            ->assertStatus(422)->assertJsonValidationErrors('due_days');
        $this->putJson('/api/payment-settings', $this->validPayload(['reminder_interval_days' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('reminder_interval_days');
        $this->putJson('/api/payment-settings', $this->validPayload(['payment_instructions' => str_repeat('a', 2001)]))
            ->assertStatus(422)->assertJsonValidationErrors('payment_instructions');
    }

    public function test_approve_uses_configured_due_days_for_the_invoice(): void
    {
        $management = $this->makeUser('management');
        Sanctum::actingAs($management);
        $this->putJson('/api/payment-settings', $this->validPayload(['due_days' => 14]))->assertOk();

        $reg = $this->makeRegistration([], $this->makeProgram());
        $this->postJson("/api/registrations/{$reg->id}/approve")->assertOk();

        $invoice = Invoice::where('registration_id', $reg->id)->firstOrFail();
        $this->assertSame(now()->addDays(14)->toDateString(), $invoice->due_date->toDateString());
    }
}
