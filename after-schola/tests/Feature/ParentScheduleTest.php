<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-10): jadwal kelas anak yang sedang aktif. Hanya anak sendiri (BR-19, AC-07).
 */
class ParentScheduleTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel();
        $this->parent = $this->makeParent();
        $this->trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $this->trainer->update(['name' => 'Kak Rina']);
        Sanctum::actingAs($this->parent);
    }

    /** Anak aktif di kelas "Coding A" dengan satu trainer utama. */
    private function activeEnrollment(User $parent, string $name = 'Budi'): Enrollment
    {
        $child = $this->makeChild($parent, $this->bdg, $this->program, 'evaluated', $name);
        $enrollment = $this->enrollStudent($this->registrationFor($child, 'terverifikasi'));
        TrainerAssignment::create(['class_group_id' => $enrollment->class_group_id, 'trainer_id' => $this->trainer->id, 'role' => 'utama']);

        return $enrollment;
    }

    private function addSession(Enrollment $enrollment, int $dayOffset, int $meetingNo, string $mode = 'onsite'): ClassSession
    {
        return ClassSession::create([
            'class_group_id' => $enrollment->class_group_id, 'trainer_id' => $this->trainer->id, 'mode' => $mode,
            'date' => today()->addDays($dayOffset), 'start_time' => '15:00:00', 'end_time' => '16:30:00', 'meeting_no' => $meetingNo,
        ]);
    }

    public function test_only_parents_can_see_the_schedule(): void
    {
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->getJson('/api/parent/schedule')->assertForbidden();
    }

    public function test_shows_class_trainers_and_upcoming_sessions_in_order(): void
    {
        $enrollment = $this->activeEnrollment($this->parent);
        $this->addSession($enrollment, 9, 3, 'online');
        $this->addSession($enrollment, 2, 2);
        $this->addSession($enrollment, -5, 1); // sudah lewat

        $res = $this->getJson('/api/parent/schedule')->assertOk();

        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_name', 'Budi')
            ->assertJsonPath('data.0.class.name', 'Coding A')
            ->assertJsonPath('data.0.class.program', 'Coding Kids')
            ->assertJsonPath('data.0.class.level', 'Dasar')
            ->assertJsonPath('data.0.class.branch', 'Cabang Bandung')
            ->assertJsonPath('data.0.trainers.0.name', 'Kak Rina')
            ->assertJsonPath('data.0.trainers.0.role', 'utama');
        $sessions = collect($res->json('data.0.sessions'));
        $this->assertSame([2, 3], $sessions->pluck('meeting_no')->all()); // yang sudah lewat tidak tampil, urut tanggal
        $this->assertSame(['onsite', 'online'], $sessions->pluck('mode')->all());
        $this->assertSame('15:00', $sessions[0]['start_time']);
        $this->assertSame('16:30', $sessions[0]['end_time']);
        $this->assertSame(today()->addDays(2)->toDateString(), $sessions[0]['date']);
        $this->assertSame('Kak Rina', $sessions[0]['trainer']);
    }

    public function test_child_without_an_active_class_has_no_schedule(): void
    {
        $this->makeChild($this->parent, $this->bdg, $this->program);
        $enrollment = $this->activeEnrollment($this->parent, 'Selesai');
        $enrollment->update(['status' => 'selesai']);

        $this->getJson('/api/parent/schedule')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_shows_one_entry_per_active_child_and_never_other_parents_classes(): void
    {
        $this->addSession($this->activeEnrollment($this->parent, 'Budi'), 1, 1);
        $this->addSession($this->activeEnrollment($this->parent, 'Siti'), 2, 1);
        $other = $this->activeEnrollment($this->makeParent('Ibu Lain'), 'Anak Lain');
        $this->addSession($other, 1, 1);

        $res = $this->getJson('/api/parent/schedule')->assertOk();

        $this->assertEqualsCanonicalizing(['Budi', 'Siti'], collect($res->json('data'))->pluck('student_name')->all());
    }
}
