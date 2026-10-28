<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 (bagian verifikasi): antrian, setujui (+ invoice otomatis), minta revisi, kirim ulang.
 */
class RegistrationVerificationTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function asManagement(): void
    {
        Sanctum::actingAs($this->makeUser('management'));
    }

    public function test_queue_excludes_drafts_and_supports_status_filter(): void
    {
        $this->makeRegistration(['status' => 'draft', 'student_name' => 'Anak Draft']);
        $this->makeRegistration(['status' => 'menunggu_verifikasi', 'student_name' => 'Anak Antri']);
        $this->makeRegistration(['status' => 'perlu_revisi', 'student_name' => 'Anak Revisi']);
        $this->asManagement();

        $all = collect($this->getJson('/api/registrations')->assertOk()->json('data'))->pluck('student_name')->all();
        $this->assertEqualsCanonicalizing(['Anak Antri', 'Anak Revisi'], $all);

        $filtered = collect($this->getJson('/api/registrations?status=perlu_revisi')->assertOk()->json('data'))->pluck('student_name')->all();
        $this->assertSame(['Anak Revisi'], $filtered);
    }

    public function test_only_users_with_verify_permission_can_access(): void
    {
        $reg = $this->makeRegistration();

        $this->getJson('/api/registrations')->assertStatus(401);

        foreach (['trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/registrations')->assertStatus(403);
            $this->postJson("/api/registrations/{$reg->id}/approve")->assertStatus(403);
            $this->postJson("/api/registrations/{$reg->id}/request-revision", ['note' => 'x'])->assertStatus(403);
        }
    }

    public function test_approve_marks_verified_and_issues_invoice_from_program_price(): void
    {
        $program = $this->makeProgram(750000);
        $reg = $this->makeRegistration([], $program);
        $manager = $this->makeUser('management');
        Sanctum::actingAs($manager);

        $this->postJson("/api/registrations/{$reg->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'terverifikasi')
            ->assertJsonPath('data.invoice.status', 'unpaid');

        $reg->refresh();
        $this->assertSame('terverifikasi', $reg->status);
        $this->assertSame($manager->id, $reg->verified_by);
        $this->assertNotNull($reg->verified_at);

        $invoice = Invoice::firstOrFail();
        $this->assertSame($reg->id, $invoice->registration_id);
        $this->assertEquals(750000, $invoice->amount);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(now()->addDays(7)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame('INV/'.now()->format('Ym').'/0001', $invoice->number);
    }

    public function test_invoice_number_increments_within_the_month(): void
    {
        $program = $this->makeProgram();
        $first = $this->makeRegistration([], $program);
        $second = $this->makeRegistration(['student_name' => 'Siti'], $program);
        $this->asManagement();

        $this->postJson("/api/registrations/{$first->id}/approve")->assertOk();
        $this->postJson("/api/registrations/{$second->id}/approve")->assertOk();

        $this->assertSame(
            ['INV/'.now()->format('Ym').'/0001', 'INV/'.now()->format('Ym').'/0002'],
            Invoice::orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_approve_without_program_is_rejected_and_nothing_changes(): void
    {
        $reg = $this->makeRegistration(['program_id' => null]);
        $this->asManagement();

        $this->postJson("/api/registrations/{$reg->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('program');

        $this->assertSame('menunggu_verifikasi', $reg->fresh()->status);
        $this->assertSame(0, Invoice::count());
    }

    public function test_approve_only_when_waiting_for_verification(): void
    {
        $program = $this->makeProgram();
        $this->asManagement();

        foreach (['draft', 'perlu_revisi', 'terverifikasi'] as $status) {
            $reg = $this->makeRegistration(['status' => $status], $program);
            $this->postJson("/api/registrations/{$reg->id}/approve")
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }
        $this->assertSame(0, Invoice::count());
    }

    public function test_request_revision_requires_note(): void
    {
        $reg = $this->makeRegistration();
        $this->asManagement();

        $this->postJson("/api/registrations/{$reg->id}/request-revision", ['note' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
        $this->assertSame('menunggu_verifikasi', $reg->fresh()->status);
    }

    public function test_request_revision_sets_status_and_note(): void
    {
        $reg = $this->makeRegistration();
        $this->asManagement();

        $this->postJson("/api/registrations/{$reg->id}/request-revision", ['note' => 'Nomor HP wali tidak valid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'perlu_revisi')
            ->assertJsonPath('data.revision_note', 'Nomor HP wali tidak valid');
        $this->assertSame(0, Invoice::count());
    }

    public function test_request_revision_only_when_waiting_for_verification(): void
    {
        $reg = $this->makeRegistration(['status' => 'terverifikasi']);
        $this->asManagement();

        $this->postJson("/api/registrations/{$reg->id}/request-revision", ['note' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_revised_registration_can_be_edited_and_resubmitted(): void
    {
        $reg = $this->makeRegistration(['status' => 'perlu_revisi', 'revision_note' => 'Perbaiki HP']);
        $evaluationId = $reg->decision->evaluation_id;
        $this->asManagement();

        $this->putJson("/api/trial-evaluations/{$evaluationId}/registration", [
            'student_name' => 'Budi Santoso',
            'level' => 'beginner',
            'parent_name' => 'Ibu Budi',
            'parent_phone' => '081200000000',
        ])->assertOk();
        $this->postJson("/api/trial-evaluations/{$evaluationId}/registration/submit")->assertOk();

        $reg->refresh();
        $this->assertSame('menunggu_verifikasi', $reg->status);
        $this->assertSame('081200000000', $reg->parent_phone);
    }

    public function test_verified_registration_cannot_be_edited_or_resubmitted(): void
    {
        $reg = $this->makeRegistration(['status' => 'terverifikasi']);
        $evaluationId = $reg->decision->evaluation_id;
        $this->asManagement();

        $this->putJson("/api/trial-evaluations/{$evaluationId}/registration", [
            'student_name' => 'Diubah', 'level' => 'beginner',
            'parent_name' => 'Ibu', 'parent_phone' => '0812',
        ])->assertStatus(422);
        $this->postJson("/api/trial-evaluations/{$evaluationId}/registration/submit")->assertStatus(422);

        $this->assertSame('terverifikasi', $reg->fresh()->status);
    }
}
