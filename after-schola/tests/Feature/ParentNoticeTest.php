<?php

namespace Tests\Feature;

use App\Models\TrialEvaluation;
use App\Notifications\EnrollmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * SYS-03 / ORT-13: orang tua diberi tahu saat hasil trial siap dan saat pendaftaran perlu revisi (in-app + email).
 */
class ParentNoticeTest extends TestCase
{
    use MakesParentFixtures, RefreshDatabase;

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->branch = $this->newBranch('BDG');
    }

    private function kinds($parent): array
    {
        return $parent->notifications->pluck('data.kind')->all();
    }

    /** Anak dengan trial yang sudah lewat, siap dinilai trainer pemilik slot. */
    private function evaluableChild($parent)
    {
        $child = $this->makeChild($parent, $this->branch, 'trial_terjadwal');
        $child->trialSlot->update(['starts_at' => now()->subDay()]);
        Sanctum::actingAs($child->trialSlot->trainer);

        return $child;
    }

    private function evaluationPayload($child, string $attendance = 'hadir'): array
    {
        return $attendance === 'hadir'
            ? ['lead_id' => $child->id, 'attendance' => 'hadir', 'score_enthusiasm' => 5, 'score_understanding' => 4, 'score_focus' => 3, 'score_collaboration' => 4, 'recommendation' => 'lanjut']
            : ['lead_id' => $child->id, 'attendance' => 'tidak_hadir'];
    }

    public function test_parent_is_notified_when_trial_result_is_ready(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->evaluableChild($parent);

        $this->postJson('/api/trial-evaluations', $this->evaluationPayload($child))->assertCreated();

        $this->assertSame(['enrollment_trial_result'], $this->kinds($parent->fresh()));
        Sanctum::actingAs($parent);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.message', 'Hasil trial Budi Santoso sudah siap');
    }

    public function test_absent_trial_does_not_notify_until_marked_present(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->evaluableChild($parent);

        $id = $this->postJson('/api/trial-evaluations', $this->evaluationPayload($child, 'tidak_hadir'))->assertCreated()->json('data.id');
        $this->assertSame([], $this->kinds($parent->fresh()));

        $this->putJson("/api/trial-evaluations/{$id}", $this->evaluationPayload($child) + ['attendance' => 'hadir'])->assertOk();
        $this->assertSame(['enrollment_trial_result'], $this->kinds($parent->fresh()));

        $this->putJson("/api/trial-evaluations/{$id}", ['strengths' => 'Aktif bertanya'])->assertOk(); // sunting biasa: tidak diulang
        $this->assertCount(1, $parent->fresh()->notifications);
    }

    public function test_parent_is_notified_with_the_note_when_registration_needs_revision(): void
    {
        Notification::fake();
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'menunggu_verifikasi');
        $registration = $child->trialEvaluation->decision->registration;
        Sanctum::actingAs($this->makeUser('management'));

        $this->postJson("/api/registrations/{$registration->id}/request-revision", ['note' => 'Nama sekolah belum lengkap'])->assertOk();

        Notification::assertSentTo($parent, EnrollmentNotification::class, function ($n) use ($parent) {
            return $n->toArray($parent)['kind'] === 'enrollment_revision'
                && str_contains($n->toMail($parent)->render(), 'Nama sekolah belum lengkap');
        });
    }

    public function test_evaluation_without_a_parent_account_is_saved_without_error(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->evaluableChild($parent);
        $child->update(['parent_id' => null]);

        $this->postJson('/api/trial-evaluations', $this->evaluationPayload($child))->assertCreated();
        $this->assertSame(1, TrialEvaluation::count());
    }
}
