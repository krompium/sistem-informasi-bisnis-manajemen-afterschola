<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-13, SYS-03): orang tua diberi tahu (in-app dan email) saat hasil trial siap, pendaftaran perlu revisi
 * atau terverifikasi (invoice terbit), dan pembayaran dikonfirmasi atau ditolak. Kegagalan email tidak membatalkan aksi staf (R-04).
 */
class ParentNotificationTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel();
        $this->parent = $this->makeParent();
    }

    /** Pemegang fungsi verifikasi pendaftaran (permission fungsi diberikan per user). */
    private function verifier(): User
    {
        return $this->makeStaff('management', 'all', [], ['verify registrations']);
    }

    private function slotTrainer($child): User
    {
        $trainer = User::findOrFail($child->trialSlot->trainer_id);
        $trainer->givePermissionTo('submit trial evaluations');

        return $trainer;
    }

    private function latest(): object
    {
        return $this->parent->fresh()->notifications()->latest()->firstOrFail();
    }

    public function test_trial_result_notifies_the_parent_when_the_child_attended(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled');
        $child->trialSlot->update(['starts_at' => now()->subDay()]);
        Sanctum::actingAs($this->slotTrainer($child));
        $payload = [
            'lead_id' => $child->id, 'attendance' => 'hadir', 'score_enthusiasm' => 4, 'score_understanding' => 4, 'score_focus' => 3,
            'score_collaboration' => 5, 'strengths' => 'Cepat paham', 'improvements' => 'Fokus',
            'recommended_level_id' => $this->program->levels()->first()->id, 'recommendation' => 'lanjut',
        ];

        $this->postJson('/api/trial-evaluations', $payload)->assertCreated();

        $notification = $this->latest();
        $this->assertSame('trial_result', $notification->data['kind']);
        $this->assertSame($child->id, $notification->data['lead_id']);
        $this->assertStringContainsString('Budi', $notification->data['message']);
    }

    public function test_no_trial_result_notification_when_the_child_was_absent(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled');
        $child->trialSlot->update(['starts_at' => now()->subDay()]);
        Sanctum::actingAs($this->slotTrainer($child));

        $this->postJson('/api/trial-evaluations', ['lead_id' => $child->id, 'attendance' => 'tidak_hadir'])->assertCreated();

        $this->assertSame(0, $this->parent->notifications()->count());
    }

    public function test_revision_request_notifies_the_parent_with_the_note(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $registration = $this->registrationFor($child, 'menunggu_verifikasi');
        Sanctum::actingAs($this->verifier());

        $this->postJson("/api/registrations/{$registration->id}/request-revision", ['note' => 'Nomor telepon belum benar'])->assertOk();

        $notification = $this->latest();
        $this->assertSame('registration_revision', $notification->data['kind']);
        $this->assertSame($child->id, $notification->data['lead_id']);
        $this->assertStringContainsString('Nomor telepon belum benar', $notification->data['message']);
    }

    public function test_verification_notifies_the_parent_that_the_invoice_was_issued(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $registration = $this->registrationFor($child, 'menunggu_verifikasi');
        Sanctum::actingAs($this->verifier());

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        $invoice = $registration->invoices()->sole();
        $notification = $this->latest();
        $this->assertSame('registration_verified', $notification->data['kind']);
        $this->assertSame($invoice->id, $notification->data['invoice_id']);
        $this->assertStringContainsString($invoice->number, $notification->data['message']);
    }

    public function test_payment_confirmation_and_rejection_notify_the_parent(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $invoice = $this->invoiceFor($this->registrationFor($child, 'terverifikasi'));
        $pending = fn () => Payment::create(['invoice_id' => $invoice->id, 'amount' => $invoice->amount, 'method' => 'tunai', 'paid_date' => today(), 'status' => 'pending']);
        Sanctum::actingAs($this->makeStaff('finance', 'all'));

        $this->postJson('/api/payments/'.$pending()->id.'/reject', ['reason' => 'Bukti tidak terbaca'])->assertOk();
        $rejected = $this->latest();
        $this->assertSame('payment_rejected', $rejected->data['kind']);
        $this->assertSame($invoice->id, $rejected->data['invoice_id']);
        $this->assertStringContainsString('Bukti tidak terbaca', $rejected->data['message']);

        $this->postJson('/api/payments/'.$pending()->id.'/confirm')->assertOk();
        $this->assertSame(1, $this->parent->notifications()->where('data->kind', 'payment_confirmed')->count());
    }

    public function test_email_failure_does_not_break_the_staff_action_and_keeps_the_in_app_notification(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            if ($event->channel === 'mail') {
                throw new \RuntimeException('SMTP down');
            }
        });
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $registration = $this->registrationFor($child, 'menunggu_verifikasi');
        Sanctum::actingAs($this->verifier());

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        $this->assertSame('terverifikasi', $registration->fresh()->status);
        $this->assertSame('registration_verified', $this->latest()->data['kind']);
    }

    public function test_registration_without_a_parent_account_still_works(): void
    {
        $registration = $this->makeStaffRegistrationWithoutLead();
        Sanctum::actingAs($this->verifier());

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();
    }

    public function test_parent_reads_notifications_with_links_to_the_related_records(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $registration = $this->registrationFor($child, 'menunggu_verifikasi');
        Sanctum::actingAs($this->verifier());
        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        Sanctum::actingAs($this->parent);
        $res = $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $res->assertJsonPath('data.0.kind', 'registration_verified')
            ->assertJsonPath('data.0.lead_id', $child->id)
            ->assertJsonPath('data.0.invoice_id', $registration->invoices()->sole()->id);

        $id = $res->json('data.0.id');
        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);

        Sanctum::actingAs($this->makeParent('Ibu Lain'));
        $this->postJson("/api/notifications/{$id}/read")->assertNotFound();
    }

    private function makeStaffRegistrationWithoutLead()
    {
        $invoiceFixtures = $this->registrationFor($this->makeChild($this->parent, $this->bdg, $this->program), 'menunggu_verifikasi');
        $invoiceFixtures->update(['lead_id' => null]);

        return $invoiceFixtures;
    }
}
