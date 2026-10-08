<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'education_level' => 'smp',
            'origin_school' => 'SMP Negeri 5 Bandung',
            'parent_email' => 'ortu.budi@example.com',
        ], $overrides);
    }

    public function test_submit_valid_lead_creates_record_with_default_status(): void
    {
        $res = $this->postJson('/api/leads', $this->validPayload());

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.status', 'leads');

        $this->assertDatabaseHas('leads', [
            'name' => 'Budi Santoso',
            'status' => 'leads',
        ]);
    }

    public function test_name_is_required(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_education_level_is_required(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['education_level' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('education_level');
    }

    public function test_education_level_must_be_valid_enum(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['education_level' => 'kuliah']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('education_level');
    }

    public function test_origin_school_is_required(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['origin_school' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('origin_school');
    }

    public function test_parent_email_is_required(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['parent_email' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_email');
    }

    public function test_parent_email_must_be_valid_format(): void
    {
        $this->postJson('/api/leads', $this->validPayload(['parent_email' => 'bukan-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_email');
    }

    public function test_program_and_schedule_are_optional(): void
    {
        $res = $this->postJson('/api/leads', $this->validPayload());

        $res->assertStatus(201);
        $this->assertDatabaseHas('leads', [
            'name' => 'Budi Santoso',
            'program_id' => null,
            'program_trial_schedule_id' => null,
        ]);
    }

    public function test_endpoint_is_public_without_token(): void
    {
        // Tanpa header Authorization sama sekali — harus tetap diproses (bukan 401).
        $res = $this->postJson('/api/leads', $this->validPayload());

        $res->assertStatus(201);
    }

    public function test_rate_limit_blocks_after_five_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/leads', $this->validPayload())
                ->assertStatus(201);
        }

        $this->postJson('/api/leads', $this->validPayload())
            ->assertStatus(429);
    }
}
