<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 6a (MGT-33): penugasan trainer ke kelas sebagai utama atau asisten.
 */
class TrainerAssignmentTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private User $staff;

    private ClassGroup $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->class = $this->classIn($this->bdg);
        $this->staff = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['assign trainers']);
        Sanctum::actingAs($this->staff);
    }

    private function classIn(Branch $branch): ClassGroup
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create([
            'program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function trainer(Branch $branch): User
    {
        return $this->makeStaff('trainer', 'assigned', [$branch->id]);
    }

    private function assign(array $overrides = [])
    {
        return $this->postJson('/api/trainer-assignments', $overrides + [
            'class_group_id' => $this->class->id,
            'trainer_id' => $this->trainer($this->bdg)->id,
            'role' => 'utama',
        ]);
    }

    public function test_staff_can_assign_main_and_assistant_trainers(): void
    {
        $main = $this->trainer($this->bdg);
        $assistant = $this->trainer($this->bdg);

        $this->assign(['trainer_id' => $main->id, 'role' => 'utama'])->assertCreated()
            ->assertJsonPath('data.role', 'utama')->assertJsonPath('data.trainer.id', $main->id);
        $this->assign(['trainer_id' => $assistant->id, 'role' => 'asisten'])->assertCreated();

        $this->assertSame(2, $this->class->assignments()->count());
        $this->getJson("/api/class-groups/{$this->class->id}")->assertJsonCount(2, 'data.assignments');
    }

    public function test_same_trainer_cannot_be_assigned_twice_to_the_same_class(): void
    {
        $trainer = $this->trainer($this->bdg);
        $this->assign(['trainer_id' => $trainer->id])->assertCreated();

        $this->assign(['trainer_id' => $trainer->id, 'role' => 'asisten'])
            ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        $this->assertSame(1, TrainerAssignment::count());
    }

    public function test_only_active_trainers_with_access_to_the_class_branch_can_be_assigned(): void
    {
        $notTrainer = $this->makeStaff('management', 'assigned', [$this->bdg->id]);
        $inactive = $this->trainer($this->bdg);
        $inactive->update(['is_active' => false]);
        $otherBranch = $this->trainer($this->jkt);

        foreach ([$notTrainer, $inactive, $otherBranch] as $invalid) {
            $this->assign(['trainer_id' => $invalid->id])->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        }
        $this->assign(['trainer_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        $this->assign(['class_group_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('class_group_id');
        $this->assign(['role' => 'kepala'])->assertStatus(422)->assertJsonValidationErrors('role');
        $this->assertSame(0, TrainerAssignment::count());
    }

    public function test_role_can_be_changed_and_assignment_removed(): void
    {
        $assignment = TrainerAssignment::create([
            'class_group_id' => $this->class->id, 'trainer_id' => $this->trainer($this->bdg)->id, 'role' => 'utama',
        ]);

        $this->putJson("/api/trainer-assignments/{$assignment->id}", ['role' => 'asisten'])
            ->assertOk()->assertJsonPath('data.role', 'asisten');
        $this->putJson("/api/trainer-assignments/{$assignment->id}", ['role' => 'kepala'])->assertStatus(422);

        $this->deleteJson("/api/trainer-assignments/{$assignment->id}")->assertNoContent();
        $this->assertDatabaseMissing('trainer_assignments', ['id' => $assignment->id]);
    }

    public function test_class_in_other_branch_returns_404_on_assign_update_and_remove(): void
    {
        $otherClass = $this->classIn($this->jkt);
        $existing = TrainerAssignment::create([
            'class_group_id' => $otherClass->id, 'trainer_id' => $this->trainer($this->jkt)->id, 'role' => 'utama',
        ]);

        $this->assign(['class_group_id' => $otherClass->id, 'trainer_id' => $this->trainer($this->jkt)->id])->assertNotFound();
        $this->putJson("/api/trainer-assignments/{$existing->id}", ['role' => 'asisten'])->assertNotFound();
        $this->deleteJson("/api/trainer-assignments/{$existing->id}")->assertNotFound();

        $this->assertSame('utama', $existing->fresh()->role);
        $this->assertSame(1, TrainerAssignment::count());
    }

    public function test_requires_assign_trainers_permission(): void
    {
        $assignment = TrainerAssignment::create([
            'class_group_id' => $this->class->id, 'trainer_id' => $this->trainer($this->bdg)->id, 'role' => 'utama',
        ]);

        foreach ([
            $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']), // punya izin kelas, bukan penugasan
            $this->trainer($this->bdg),
        ] as $user) {
            Sanctum::actingAs($user);
            $this->assign()->assertForbidden();
            $this->putJson("/api/trainer-assignments/{$assignment->id}", ['role' => 'asisten'])->assertForbidden();
            $this->deleteJson("/api/trainer-assignments/{$assignment->id}")->assertForbidden();
        }
        $this->assertSame(1, TrainerAssignment::count());
    }
}
