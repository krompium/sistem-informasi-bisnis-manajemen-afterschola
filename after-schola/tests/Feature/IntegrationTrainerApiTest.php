<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\TrainerAssignment;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * KEU-08, SYS-05, AC-13 (PRD 10.4): API integrasi read-only untuk absensi trainer.
 * Tim Keuangan menghitung honor dari data ini (D-02, BR-20). Token ber-ability attendance:read, dibatasi cabang akun layanan.
 */
class IntegrationTrainerApiTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private const RANGE = ['from' => '2026-11-01', 'to' => '2026-11-30'];

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private ClassGroup $group;

    private User $main;

    private User $assistant;

    private User $substitute;

    private User $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgramWithLevel();
        $this->group = $this->classGroup($this->bdg, 'Coding A');
        $this->main = $this->trainer('Utama', 'KEU-001');
        $this->assistant = $this->trainer('Asisten', null);
        $this->substitute = $this->trainer('Pengganti', 'KEU-003');
        TrainerAssignment::create(['class_group_id' => $this->group->id, 'trainer_id' => $this->main->id, 'role' => 'utama']);
        TrainerAssignment::create(['class_group_id' => $this->group->id, 'trainer_id' => $this->assistant->id, 'role' => 'asisten']);
        $this->service = $this->makeStaff('finance', 'assigned', [$this->bdg->id]);
    }

    private function classGroup(Branch $branch, string $name): ClassGroup
    {
        return ClassGroup::create([
            'program_id' => $this->program->id, 'level_id' => $this->program->levels()->first()->id, 'branch_id' => $branch->id,
            'name' => $name, 'capacity' => 10, 'is_active' => true,
        ]);
    }

    private function trainer(string $name, ?string $externalRef, ?Branch $branch = null): User
    {
        $user = $this->makeStaff('trainer', 'assigned', [($branch ?? $this->bdg)->id]);
        $user->forceFill(['name' => $name, 'external_ref' => $externalRef])->save();

        return $user;
    }

    /** Satu sesi reguler dengan absensi trainer pelaksananya. */
    private function attendance(User $trainer, string $date, string $status = 'hadir', ?ClassGroup $group = null, string $mode = 'onsite'): TrainerAttendance
    {
        $session = ClassSession::create([
            'class_group_id' => ($group ?? $this->group)->id, 'trainer_id' => $trainer->id, 'date' => $date,
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => ClassSession::count() + 1, 'mode' => $mode,
        ]);

        return TrainerAttendance::create([
            'class_session_id' => $session->id, 'trainer_id' => $trainer->id, 'status' => $status,
            'check_in_at' => $date.' 02:05:00', 'latitude' => $mode === 'onsite' ? -6.9 : null, 'longitude' => $mode === 'onsite' ? 107.6 : null,
        ]);
    }

    /** Request dengan token sungguhan (bukan Sanctum::actingAs) supaya ability benar-benar dievaluasi. */
    private function withIntegrationToken(User $user, array $abilities = ['attendance:read']): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('integration:Test', $abilities)->plainTextToken);
    }

    private function get_(string $path, array $query = [], ?User $as = null)
    {
        return $this->withIntegrationToken($as ?? $this->service)->getJson("/api/integration/v1/$path?".http_build_query($query));
    }

    // ---- trainer-attendances: kontrak PRD 10.4 ----

    public function test_trainer_attendances_follow_the_contract_and_derive_the_assignment_role(): void
    {
        $this->attendance($this->main, '2026-11-03');
        $this->attendance($this->assistant, '2026-11-04', 'terlambat');
        $this->attendance($this->substitute, '2026-11-05', 'hadir', null, 'online');

        $rows = $this->get_('trainer-attendances', self::RANGE)->assertOk()->json('data');

        $this->assertCount(3, $rows);
        $this->assertSame([
            'id' => $rows[0]['id'],
            'session_date' => '2026-11-03',
            'branch_code' => 'BDG',
            'program' => $this->program->name,
            'class_group' => 'Coding A',
            'trainer_id' => $this->main->id,
            'trainer_name' => 'Utama',
            'trainer_external_ref' => 'KEU-001',
            'assignment_role' => 'utama',
            'mode' => 'onsite',
            'status' => 'hadir',
            'check_in_at' => $rows[0]['check_in_at'],
        ], $rows[0]);
        $this->assertStringStartsWith('2026-11-03T02:05:00', $rows[0]['check_in_at']);
        $this->assertSame(['utama', 'asisten', 'pengganti'], array_column($rows, 'assignment_role'));
        $this->assertSame(['hadir', 'terlambat', 'hadir'], array_column($rows, 'status'));
        $this->assertSame([null, 'KEU-003'], [$rows[1]['trainer_external_ref'], $rows[2]['trainer_external_ref']]);
        $this->assertSame('online', $rows[2]['mode']);
    }

    public function test_from_and_to_are_required_and_must_be_ordered(): void
    {
        $this->get_('trainer-attendances')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->get_('trainer-attendances', ['from' => '2026-11-30', 'to' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_period_is_inclusive_and_filters_by_trainer(): void
    {
        $this->attendance($this->main, '2026-10-31');
        $first = $this->attendance($this->main, '2026-11-01');
        $last = $this->attendance($this->assistant, '2026-11-30');
        $this->attendance($this->main, '2026-12-01');

        $this->assertSame([$first->id, $last->id], array_column($this->get_('trainer-attendances', self::RANGE)->assertOk()->json('data'), 'id'));
        $this->assertSame([$last->id], array_column($this->get_('trainer-attendances', self::RANGE + ['trainer_id' => $this->assistant->id])->json('data'), 'id'));
    }

    public function test_data_is_limited_to_the_service_accounts_branches(): void
    {
        $jktGroup = $this->classGroup($this->jkt, 'Robotik J');
        $jktTrainer = $this->trainer('Trainer Jakarta', 'KEU-900', $this->jkt);
        $mine = $this->attendance($this->main, '2026-11-03');
        $theirs = $this->attendance($jktTrainer, '2026-11-03', 'hadir', $jktGroup);

        $this->assertSame([$mine->id], array_column($this->get_('trainer-attendances', self::RANGE)->json('data'), 'id'));
        $this->assertSame([], $this->get_('trainer-attendances', self::RANGE + ['branch_id' => $this->jkt->id])->json('data'));

        $all = $this->makeStaff('finance', 'all');
        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], array_column($this->get_('trainer-attendances', self::RANGE, $all)->json('data'), 'id'));
        $this->assertSame([$theirs->id], array_column($this->get_('trainer-attendances', self::RANGE + ['branch_id' => $this->jkt->id], $all)->json('data'), 'id'));
    }

    public function test_pagination_is_stable_and_ordered_by_id(): void
    {
        $ids = collect([3, 4, 5])->map(fn ($day) => $this->attendance($this->main, "2026-11-0$day")->id)->all();

        $page1 = $this->get_('trainer-attendances', self::RANGE + ['per_page' => 2])->assertOk();
        $page2 = $this->get_('trainer-attendances', self::RANGE + ['per_page' => 2, 'page' => 2])->assertOk();

        $this->assertSame([$ids[0], $ids[1]], array_column($page1->json('data'), 'id'));
        $this->assertSame([$ids[2]], array_column($page2->json('data'), 'id'));
        $this->assertSame(3, $page1->json('meta.total'));
    }

    // ---- ability (AC-13) ----

    public function test_trainer_endpoints_need_the_attendance_read_ability(): void
    {
        $this->attendance($this->main, '2026-11-03');

        $this->getJson('/api/integration/v1/trainer-attendances?'.http_build_query(self::RANGE))->assertUnauthorized();
        $this->withIntegrationToken($this->service, ['finance:read'])->getJson('/api/integration/v1/trainer-attendances?'.http_build_query(self::RANGE))->assertForbidden();
        $this->withIntegrationToken($this->service, ['finance:read'])->getJson('/api/integration/v1/trainers')->assertForbidden();
        $this->get_('trainer-attendances', self::RANGE)->assertOk();
        $this->get_('trainers')->assertOk();
    }

    public function test_an_attendance_token_cannot_read_finance_data_and_cannot_be_used_in_the_staff_api(): void
    {
        $this->get_('invoices')->assertForbidden();
        $this->withIntegrationToken($this->service)->getJson('/api/me')->assertForbidden();
    }

    public function test_a_revoked_token_gets_401(): void
    {
        $token = $this->service->createToken('integration:Test', ['attendance:read']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/integration/v1/trainers')->assertOk();

        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/integration/v1/trainers')->assertUnauthorized();
    }

    public function test_only_accounts_allowed_to_export_trainer_attendance_can_use_it(): void
    {
        $this->get_('trainers', [], $this->makeStaff('trainer', 'assigned', [$this->bdg->id]))->assertForbidden();
    }

    // ---- trainers + external_ref ----

    public function test_trainers_lists_only_trainers_of_the_service_accounts_branches(): void
    {
        $jktTrainer = $this->trainer('Trainer Jakarta', 'KEU-900', $this->jkt);
        $this->makeStaff('management', 'assigned', [$this->bdg->id]); // bukan trainer

        $rows = $this->get_('trainers')->assertOk()->json('data');

        $this->assertSame(
            [['name' => 'Utama', 'external_ref' => 'KEU-001'], ['name' => 'Asisten', 'external_ref' => null], ['name' => 'Pengganti', 'external_ref' => 'KEU-003']],
            collect($rows)->map(fn ($row) => ['name' => $row['name'], 'external_ref' => $row['external_ref']])->all()
        );
        $this->assertNotContains($jktTrainer->id, array_column($rows, 'id'));
        $this->assertSame(['id', 'name', 'external_ref', 'is_active'], array_keys($rows[0]));
    }

    public function test_management_can_set_a_trainers_external_ref_and_it_shows_up_in_the_api(): void
    {
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage users']));

        $this->putJson("/api/users/{$this->assistant->id}", ['external_ref' => 'KEU-002'])
            ->assertOk()->assertJsonPath('data.external_ref', 'KEU-002');
        $this->putJson("/api/users/{$this->main->id}", ['external_ref' => str_repeat('x', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors('external_ref');

        $this->app['auth']->forgetGuards();
        $byName = collect($this->get_('trainers')->json('data'))->pluck('external_ref', 'name');
        $this->assertSame('KEU-002', $byName['Asisten']);
    }
}
