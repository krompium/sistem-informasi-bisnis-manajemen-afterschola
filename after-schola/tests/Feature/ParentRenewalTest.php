<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentSetting;
use App\Models\Program;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-12; BR-18, A-05, AC-15, AC-07): ortu mengajukan perpanjangan untuk anak yang sedang aktif.
 * Registrasi perpanjangan langsung terverifikasi (tanpa verifikasi ulang) dan invoice baru terbit; enrollment berjalan tidak terputus.
 */
class ParentRenewalTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    private Lead $child;

    private Registration $original;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel();
        $this->parent = $this->makeParent();
        $this->child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $this->original = $this->registrationFor($this->child, 'terverifikasi');
        $this->invoiceFor($this->original, 'paid');
        $this->enrollment = $this->enrollStudent($this->original);
        Sanctum::actingAs($this->parent);
    }

    private function renew(?Lead $child = null)
    {
        return $this->postJson('/api/parent/children/'.($child ?? $this->child)->id.'/renewal');
    }

    public function test_renewal_creates_a_verified_renewal_registration_and_an_unpaid_invoice(): void
    {
        $this->program->update(['price' => 650000]); // invoice memakai harga program saat ini (snapshot)
        PaymentSetting::current()->update(['due_days' => 10]);

        $this->renew()->assertCreated()
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.can_pay', true);

        $renewal = Registration::where('type', 'perpanjangan')->sole();
        $this->assertSame('terverifikasi', $renewal->status);
        $this->assertNull($renewal->decision_id);
        $this->assertSame($this->original->student_id, $renewal->student_id);
        $this->assertSame($this->child->id, $renewal->lead_id);
        $this->assertSame($this->program->id, $renewal->program_id);
        $this->assertSame($this->bdg->id, $renewal->branch_id);
        $this->assertSame($this->original->student_name, $renewal->student_name);
        $this->assertSame($this->original->parent_name, $renewal->parent_name);
        $this->assertSame($this->original->level, $renewal->level);

        $invoice = $renewal->invoices()->sole();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertEquals(650000, $invoice->amount);
        $this->assertSame(now()->addDays(10)->toDateString(), $invoice->due_date->toDateString());
        $this->assertMatchesRegularExpression('#^INV/BDG/'.now()->format('Ym').'/\d{4}$#', $invoice->number);
    }

    public function test_the_running_enrollment_stays_active(): void
    {
        $this->renew()->assertCreated();

        $this->assertSame(1, Enrollment::count());
        $this->assertSame('aktif', $this->enrollment->fresh()->status);
        $this->assertNull($this->enrollment->fresh()->end_date);
    }

    public function test_the_renewal_invoice_shows_up_in_the_parents_invoice_list(): void
    {
        $this->renew()->assertCreated();

        $this->getJson('/api/parent/invoices')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_parent_is_notified_about_the_renewal_invoice(): void
    {
        $this->renew()->assertCreated();

        $notification = $this->parent->fresh()->notifications()->where('data->kind', 'renewal_invoice')->sole();
        $this->assertSame(Invoice::where('status', 'unpaid')->sole()->id, $notification->data['invoice_id']);
    }

    public function test_renewal_needs_a_child_that_is_active_in_a_class(): void
    {
        $this->enrollment->update(['status' => 'selesai', 'end_date' => today()]);

        $this->renew()->assertUnprocessable()->assertJsonValidationErrors('renewal');
        $this->assertSame(0, Registration::where('type', 'perpanjangan')->count());
    }

    public function test_renewal_is_rejected_for_a_child_that_never_enrolled(): void
    {
        $newcomer = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Andi');

        $this->renew($newcomer)->assertUnprocessable()->assertJsonValidationErrors('renewal');
        $this->assertSame(0, Registration::where('type', 'perpanjangan')->count());
    }

    public function test_a_second_renewal_is_rejected_while_the_first_invoice_is_unpaid(): void
    {
        $this->renew()->assertCreated();
        $this->renew()->assertUnprocessable()->assertJsonValidationErrors('renewal');

        $this->assertSame(1, Registration::where('type', 'perpanjangan')->count());
        $this->assertSame(1, Invoice::where('status', 'unpaid')->count());
    }

    public function test_renewal_is_allowed_again_once_the_previous_one_is_paid(): void
    {
        $this->renew()->assertCreated();
        Invoice::where('status', 'unpaid')->update(['status' => 'paid', 'paid_at' => now()]);

        $this->renew()->assertCreated();
        $this->assertSame(2, Registration::where('type', 'perpanjangan')->count());
    }

    public function test_another_parents_child_is_404_and_nothing_is_created(): void
    {
        $other = $this->makeParent('Ibu Lain');
        $otherChild = $this->makeChild($other, $this->bdg, $this->program, 'evaluated', 'Rudi');
        $this->enrollStudent($this->registrationFor($otherChild, 'terverifikasi'));

        $this->renew($otherChild)->assertNotFound();
        $this->assertSame(0, Registration::where('type', 'perpanjangan')->count());
    }

    public function test_only_parents_can_renew(): void
    {
        Sanctum::actingAs($this->makeStaff('trainer', 'assigned', [$this->bdg->id]));

        $this->renew()->assertForbidden();
    }

    public function test_verifying_a_new_registration_still_issues_exactly_one_invoice(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Siti');
        $registration = $this->registrationFor($child, 'menunggu_verifikasi');
        $verifier = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['verify registrations']);
        Sanctum::actingAs($verifier);

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        $invoice = $registration->invoices()->sole();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertEquals($this->program->price, $invoice->amount);
        $this->assertMatchesRegularExpression('#^INV/BDG/#', $invoice->number);
    }
}
