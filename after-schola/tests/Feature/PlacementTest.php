<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 6b (MGT-31, MGT-32): penempatan siswa ke kelas. BR-09 (harus Lunas), BR-10 (level sama
 * dengan rekomendasi trial, override beralasan), BR-11 (kapasitas), isolasi cabang (BR-19).
 */
class PlacementTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private ProgramLevel $beginner;

    private ProgramLevel $intermediate;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram();
        $this->beginner = $this->program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        $this->intermediate = $this->program->levels()->create(['name' => 'Intermediate', 'sort_order' => 2]);
        $this->staff = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']);
        Sanctum::actingAs($this->staff);
    }

    /** Registrasi terverifikasi; $invoice = status invoice ('paid', 'unpaid', 'void') atau null tanpa invoice. */
    private function registration(Branch $branch, ?ProgramLevel $recommended = null, ?string $invoice = 'paid', array $overrides = []): Registration
    {
        $registration = $this->makeRegistration(
            $overrides + ['status' => 'terverifikasi', 'branch_id' => $branch->id, 'student_name' => 'Siswa '.uniqid()],
            $this->program
        );
        $registration->decision->evaluation->update(['recommended_level_id' => $recommended?->id]);
        if ($invoice) {
            $this->makeInvoice($registration, ['status' => $invoice, 'paid_at' => $invoice === 'paid' ? now() : null]);
        }

        return $registration;
    }

    private function classIn(Branch $branch, ?ProgramLevel $level = null, array $overrides = []): ClassGroup
    {
        return ClassGroup::create($overrides + [
            'program_id' => $this->program->id, 'level_id' => ($level ?? $this->beginner)->id,
            'branch_id' => $branch->id, 'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function enroll(ClassGroup $class, string $status = 'aktif', ?Student $student = null): Enrollment
    {
        return Enrollment::create([
            'class_group_id' => $class->id,
            'student_id' => ($student ?? Student::create(['name' => 'Murid '.uniqid(), 'branch_id' => $class->branch_id]))->id,
            'start_date' => '2026-10-01',
            'status' => $status,
        ]);
    }

    private function place(Registration $registration, ClassGroup $class, array $extra = [])
    {
        return $this->postJson('/api/enrollments', ['registration_id' => $registration->id, 'class_group_id' => $class->id] + $extra);
    }

    public function test_places_a_paid_registration_and_creates_the_student_with_parent_and_branch(): void
    {
        $parent = $this->makeStaff('parent');
        $lead = Lead::create([
            'name' => 'Budi', 'education_level' => 'smp', 'origin_school' => 'SMP 5', 'parent_email' => $parent->email,
            'parent_id' => $parent->id, 'program_id' => $this->program->id, 'status' => 'trial_scheduled',
        ]);
        $registration = $this->registration($this->bdg, $this->beginner, 'paid', ['lead_id' => $lead->id, 'student_name' => 'Budi Santoso']);
        $class = $this->classIn($this->bdg, $this->beginner);

        $this->place($registration, $class)->assertCreated()
            ->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.class_group.id', $class->id)
            ->assertJsonPath('data.student.name', 'Budi Santoso')
            ->assertJsonPath('data.start_date', now(config('app.display_timezone'))->toDateString());

        $student = Student::firstOrFail();
        $this->assertSame($this->bdg->id, $student->branch_id);
        $this->assertSame($parent->id, $student->parent_id);
        $this->assertSame($student->id, $registration->fresh()->student_id);
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->where('status', 'aktif')->count());
    }

    public function test_reuses_the_existing_student_of_the_registration(): void
    {
        $student = Student::create(['name' => 'Sudah Ada', 'branch_id' => $this->bdg->id]);
        $registration = $this->registration($this->bdg, $this->beginner, 'paid', ['student_id' => $student->id]);

        $this->place($registration, $this->classIn($this->bdg))->assertCreated()->assertJsonPath('data.student.id', $student->id);

        $this->assertSame(1, Student::count());
    }

    public function test_registration_must_be_verified_and_fully_paid(): void
    {
        $class = $this->classIn($this->bdg);

        foreach ([
            $this->registration($this->bdg, null, 'unpaid'),
            $this->registration($this->bdg, null, null),
            $this->registration($this->bdg, null, 'void'),
            $this->registration($this->bdg, null, 'paid', ['status' => 'menunggu_verifikasi']),
        ] as $registration) {
            $this->place($registration, $class)->assertStatus(422)->assertJsonValidationErrors('registration');
        }

        $this->assertSame(0, Enrollment::count());
        $this->assertSame(0, Student::count());
    }

    public function test_class_must_be_active_and_match_program_and_branch_of_the_registration(): void
    {
        $registration = $this->registration($this->bdg, $this->beginner);
        $otherProgram = Program::create(['name' => 'Robotik', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 1, 'is_active' => true]);
        $otherLevel = $otherProgram->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        $sameBranchOtherProgram = $this->classIn($this->bdg, null, ['program_id' => $otherProgram->id, 'level_id' => $otherLevel->id]);
        $staffAll = $this->makeStaff('management', 'all', [], ['manage classes']);
        Sanctum::actingAs($staffAll);

        $this->place($registration, $sameBranchOtherProgram)->assertStatus(422)->assertJsonValidationErrors('class_group_id');
        $this->place($registration, $this->classIn($this->jkt))->assertStatus(422)->assertJsonValidationErrors('class_group_id');
        $this->place($registration, $this->classIn($this->bdg, null, ['is_active' => false]))->assertStatus(422)->assertJsonValidationErrors('class_group_id');
        $this->assertSame(0, Enrollment::count());
    }

    public function test_class_level_must_match_the_trial_recommendation_unless_overridden_with_a_reason(): void
    {
        $registration = $this->registration($this->bdg, $this->beginner);
        $intermediateClass = $this->classIn($this->bdg, $this->intermediate);

        $this->place($registration, $intermediateClass)->assertStatus(422)->assertJsonValidationErrors('level_override_reason');
        $this->assertSame(0, Enrollment::count());

        $this->place($registration, $intermediateClass, ['level_override_reason' => 'Orang tua minta naik level'])
            ->assertCreated()->assertJsonPath('data.level_override_reason', 'Orang tua minta naik level');
        $this->assertSame('Orang tua minta naik level', Enrollment::firstOrFail()->level_override_reason);
    }

    public function test_matching_level_or_missing_recommendation_needs_no_override(): void
    {
        $this->place($this->registration($this->bdg, $this->beginner), $this->classIn($this->bdg, $this->beginner))
            ->assertCreated()->assertJsonPath('data.level_override_reason', null);

        // Tanpa rekomendasi trial: level kelas bebas.
        $this->place($this->registration($this->bdg, null), $this->classIn($this->bdg, $this->intermediate))->assertCreated();
    }

    public function test_full_class_rejects_placement_but_ended_enrollments_do_not_count(): void
    {
        $class = $this->classIn($this->bdg, null, ['capacity' => 2]);
        $this->enroll($class);
        $this->enroll($class, 'selesai');
        $this->enroll($class, 'pindah');

        $this->place($this->registration($this->bdg, $this->beginner), $class)->assertCreated();   // kursi ke-2
        $this->place($this->registration($this->bdg, $this->beginner), $class)
            ->assertStatus(422)->assertJsonValidationErrors('class_group_id');
    }

    public function test_student_cannot_have_two_active_enrollments_or_be_placed_twice(): void
    {
        $student = Student::create(['name' => 'Aktif', 'branch_id' => $this->bdg->id]);
        $this->enroll($this->classIn($this->bdg), 'aktif', $student);
        $registration = $this->registration($this->bdg, $this->beginner, 'paid', ['student_id' => $student->id]);

        $this->place($registration, $this->classIn($this->bdg))->assertStatus(422)->assertJsonValidationErrors('registration');

        // Kirim ganda untuk registrasi yang sama.
        $fresh = $this->registration($this->bdg, $this->beginner);
        $class = $this->classIn($this->bdg);
        $this->place($fresh, $class)->assertCreated();
        $this->place($fresh, $class)->assertStatus(422)->assertJsonValidationErrors('registration');
        $this->assertSame(2, Enrollment::where('status', 'aktif')->count());
    }

    public function test_registration_or_class_of_another_branch_returns_404(): void
    {
        $this->place($this->registration($this->jkt, $this->beginner), $this->classIn($this->bdg))->assertNotFound();
        $this->place($this->registration($this->bdg, $this->beginner), $this->classIn($this->jkt))->assertNotFound();

        $this->assertSame(0, Enrollment::count());
        $this->assertSame(0, Student::count());
    }

    public function test_requires_manage_classes_permission(): void
    {
        $registration = $this->registration($this->bdg, $this->beginner);
        $class = $this->classIn($this->bdg);

        foreach ([
            $this->makeStaff('management', 'assigned', [$this->bdg->id], ['assign trainers']),
            $this->makeStaff('trainer', 'assigned', [$this->bdg->id]),
            $this->makeStaff('parent'),
        ] as $user) {
            Sanctum::actingAs($user);
            $this->place($registration, $class)->assertForbidden();
            $this->getJson('/api/placement-candidates')->assertForbidden();
            $this->getJson('/api/enrollments')->assertForbidden();
        }
        $this->assertSame(0, Enrollment::count());
    }

    public function test_candidates_list_only_verified_paid_unplaced_registrations_in_scope(): void
    {
        $ready = $this->registration($this->bdg, $this->intermediate, 'paid', ['student_name' => 'Siap Ditempatkan']);
        $this->registration($this->bdg, null, 'unpaid');
        $this->registration($this->bdg, null, 'void');
        $this->registration($this->bdg, null, 'paid', ['status' => 'menunggu_verifikasi']);
        $otherBranch = $this->registration($this->jkt, null, 'paid');
        $placed = $this->registration($this->bdg, null, 'paid');
        $this->place($placed, $this->classIn($this->bdg))->assertCreated();

        $res = $this->getJson('/api/placement-candidates')->assertOk();
        $this->assertEquals([$ready->id], $res->json('data.*.id'));
        $res->assertJsonPath('data.0.student_name', 'Siap Ditempatkan')
            ->assertJsonPath('data.0.recommended_level.id', $this->intermediate->id)
            ->assertJsonPath('data.0.program.id', $this->program->id)
            ->assertJsonPath('data.0.branch.id', $this->bdg->id);

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage classes']));
        $this->assertEqualsCanonicalizing([$ready->id, $otherBranch->id], $this->getJson('/api/placement-candidates')->json('data.*.id'));
        $this->assertEquals([$otherBranch->id], $this->getJson("/api/placement-candidates?branch_id={$this->jkt->id}")->json('data.*.id'));
    }

    public function test_class_roster_is_scoped_and_exposes_enrolled_count(): void
    {
        $mine = $this->classIn($this->bdg);
        $other = $this->classIn($this->jkt);
        $active = $this->enroll($mine);
        $this->enroll($mine, 'selesai');
        $this->enroll($other);

        $ids = $this->getJson("/api/enrollments?class_group_id={$mine->id}")->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$active->id, Enrollment::where('class_group_id', $mine->id)->where('status', 'selesai')->value('id')], $ids);
        $this->assertSame([], $this->getJson("/api/enrollments?class_group_id={$other->id}")->assertOk()->json('data'));

        $this->getJson("/api/class-groups/{$mine->id}")->assertOk()->assertJsonPath('data.enrolled_count', 1);
        $this->getJson('/api/class-groups')->assertOk()->assertJsonPath('data.0.enrolled_count', 1);
    }

    public function test_class_capacity_and_identity_are_locked_by_enrollments(): void
    {
        $class = $this->classIn($this->bdg, null, ['capacity' => 5]);
        $this->enroll($class);
        $this->enroll($class);
        $payload = fn (array $o = []) => $o + [
            'program_id' => $class->program_id, 'level_id' => $class->level_id, 'branch_id' => $class->branch_id,
            'name' => $class->name, 'capacity' => 5,
        ];

        $this->putJson("/api/class-groups/{$class->id}", $payload(['capacity' => 1]))->assertStatus(422)->assertJsonValidationErrors('capacity');
        $this->putJson("/api/class-groups/{$class->id}", $payload(['level_id' => $this->intermediate->id]))->assertStatus(422)->assertJsonValidationErrors('level_id');
        $this->putJson("/api/class-groups/{$class->id}", $payload(['capacity' => 2, 'name' => 'Ganti Nama']))->assertOk();
    }

    public function test_school_based_student_list_does_not_include_regular_students(): void
    {
        Student::create(['name' => 'Murid Reguler', 'branch_id' => $this->bdg->id]);
        Sanctum::actingAs($this->makeStaff('management', 'all', [], []));

        $this->assertSame([], $this->getJson('/api/students')->assertOk()->json('data'));
    }
}
