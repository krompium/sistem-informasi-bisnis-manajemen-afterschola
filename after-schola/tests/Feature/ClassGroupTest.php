<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 6a (MGT-30): CRUD kelas (class_groups). Isolasi cabang untuk staf (BR-19),
 * isolasi penugasan untuk trainer (PRD §9: kelas yang tidak ditugaskan = 404).
 */
class ClassGroupTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $coding;

    private ProgramLevel $codingLevel;

    private Program $robotik;

    private ProgramLevel $robotikLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        [$this->coding, $this->codingLevel] = $this->programWithLevel('Coding Kids');
        [$this->robotik, $this->robotikLevel] = $this->programWithLevel('Robotik');
    }

    private function programWithLevel(string $name, bool $active = true): array
    {
        $program = Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => $active]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return [$program, $level];
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'program_id' => $this->coding->id,
            'level_id' => $this->codingLevel->id,
            'branch_id' => $this->bdg->id,
            'name' => 'Coding A',
            'capacity' => 10,
        ];
    }

    private function classIn(Branch $branch, array $overrides = []): ClassGroup
    {
        return ClassGroup::create($overrides + [
            'program_id' => $this->coding->id, 'level_id' => $this->codingLevel->id,
            'branch_id' => $branch->id, 'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function classStaff(string $scope = 'assigned', ?Branch $branch = null): User
    {
        return $this->makeStaff('management', $scope, $branch ? [$branch->id] : [], ['manage classes']);
    }

    public function test_management_can_create_a_class_group(): void
    {
        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));

        $this->postJson('/api/class-groups', $this->payload())->assertCreated()
            ->assertJsonPath('data.name', 'Coding A')
            ->assertJsonPath('data.capacity', 10)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.program.id', $this->coding->id)
            ->assertJsonPath('data.level.id', $this->codingLevel->id)
            ->assertJsonPath('data.branch.id', $this->bdg->id)
            ->assertJsonPath('data.assignments', []);

        $this->assertDatabaseHas('class_groups', ['name' => 'Coding A', 'branch_id' => $this->bdg->id]);
    }

    public function test_create_validates_level_ownership_capacity_name_and_active_program(): void
    {
        Sanctum::actingAs($this->classStaff('all'));

        // Level milik program lain.
        $this->postJson('/api/class-groups', $this->payload(['level_id' => $this->robotikLevel->id]))
            ->assertStatus(422)->assertJsonValidationErrors('level_id');
        $this->postJson('/api/class-groups', $this->payload(['capacity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
        $this->postJson('/api/class-groups', $this->payload(['name' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('name');

        [$archived, $archivedLevel] = $this->programWithLevel('Program Arsip', active: false);
        $this->postJson('/api/class-groups', $this->payload(['program_id' => $archived->id, 'level_id' => $archivedLevel->id]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->assertDatabaseCount('class_groups', 0);
    }

    public function test_create_rejects_branch_outside_user_scope(): void
    {
        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));

        $this->postJson('/api/class-groups', $this->payload(['branch_id' => $this->jkt->id]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
        $this->assertDatabaseCount('class_groups', 0);
    }

    public function test_listing_is_scoped_to_branches_and_filterable(): void
    {
        $bdgCoding = $this->classIn($this->bdg);
        $bdgRobotik = $this->classIn($this->bdg, ['program_id' => $this->robotik->id, 'level_id' => $this->robotikLevel->id]);
        $jkt = $this->classIn($this->jkt);
        $archived = $this->classIn($this->bdg, ['is_active' => false]);

        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));
        $ids = $this->getJson('/api/class-groups')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$bdgCoding->id, $bdgRobotik->id, $archived->id], $ids);

        Sanctum::actingAs($this->classStaff('all'));
        $this->assertCount(4, $this->getJson('/api/class-groups')->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing([$jkt->id], $this->getJson("/api/class-groups?branch_id={$this->jkt->id}")->json('data.*.id'));
        $this->assertEqualsCanonicalizing([$bdgRobotik->id], $this->getJson("/api/class-groups?program_id={$this->robotik->id}")->json('data.*.id'));
        $this->assertEqualsCanonicalizing([$archived->id], $this->getJson('/api/class-groups?is_active=0')->json('data.*.id'));
    }

    public function test_listing_honours_per_page_up_to_a_hundred(): void
    {
        foreach (range(1, 3) as $i) {
            $this->classIn($this->bdg);
        }
        Sanctum::actingAs($this->classStaff('all'));

        $this->getJson('/api/class-groups?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
        $this->getJson('/api/class-groups?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_show_and_update_of_other_branch_class_return_404(): void
    {
        $other = $this->classIn($this->jkt, ['name' => 'Kelas Jakarta']);
        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));

        $this->getJson("/api/class-groups/{$other->id}")->assertNotFound();
        $this->putJson("/api/class-groups/{$other->id}", $this->payload(['branch_id' => $this->bdg->id, 'name' => 'Dibajak']))->assertNotFound();

        $this->assertSame('Kelas Jakarta', $other->fresh()->name);
    }

    public function test_update_changes_fields_and_archives_through_is_active(): void
    {
        $class = $this->classIn($this->bdg, ['name' => 'Lama']);
        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));

        $this->putJson("/api/class-groups/{$class->id}", $this->payload(['name' => 'Baru', 'capacity' => 12, 'is_active' => false]))
            ->assertOk()->assertJsonPath('data.name', 'Baru')->assertJsonPath('data.is_active', false);

        $this->assertSame(12, $class->fresh()->capacity);
        $this->assertFalse($class->fresh()->is_active);
    }

    public function test_trainer_sees_only_assigned_classes_and_cannot_write(): void
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $mine = $this->classIn($this->bdg);
        $notMine = $this->classIn($this->bdg);
        TrainerAssignment::create(['class_group_id' => $mine->id, 'trainer_id' => $trainer->id, 'role' => 'utama']);
        Sanctum::actingAs($trainer);

        $this->assertEqualsCanonicalizing([$mine->id], $this->getJson('/api/class-groups')->assertOk()->json('data.*.id'));
        $this->getJson("/api/class-groups/{$mine->id}")->assertOk()->assertJsonPath('data.assignments.0.role', 'utama');
        $this->getJson("/api/class-groups/{$notMine->id}")->assertNotFound();
        $this->postJson('/api/class-groups', $this->payload())->assertForbidden();
        $this->putJson("/api/class-groups/{$mine->id}", $this->payload())->assertForbidden();
    }

    public function test_guest_and_parent_are_blocked(): void
    {
        $class = $this->classIn($this->bdg);

        $this->getJson('/api/class-groups')->assertUnauthorized();

        Sanctum::actingAs($this->makeStaff('parent'));
        $this->getJson('/api/class-groups')->assertForbidden();
        $this->getJson("/api/class-groups/{$class->id}")->assertForbidden();
        $this->getJson('/api/class-groups/options')->assertForbidden();
    }

    public function test_options_expose_scoped_branches_programs_with_levels_and_trainers_by_branch(): void
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $this->makeStaff('trainer', 'assigned', [$this->jkt->id]); // tidak boleh muncul untuk staf Bandung
        Sanctum::actingAs($this->classStaff('assigned', $this->bdg));

        $res = $this->getJson('/api/class-groups/options')->assertOk();

        $res->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.id', $this->bdg->id);
        $this->assertEqualsCanonicalizing(['Coding Kids', 'Robotik'], collect($res->json('data.programs'))->pluck('name')->all());
        $this->assertSame($this->codingLevel->id, collect($res->json('data.programs'))->firstWhere('name', 'Coding Kids')['levels'][0]['id']);
        $this->assertEquals([$trainer->id], collect($res->json('data.trainers'))->pluck('id')->all());
    }
}
