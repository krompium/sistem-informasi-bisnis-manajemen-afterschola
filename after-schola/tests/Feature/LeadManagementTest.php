<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 2, slice 2.4: daftar calon dan leads, catatan, export Excel (MGT-21, MGT-22).
 * Scope cabang: lead cabang lain 404 (update) / tidak muncul (daftar dan export).
 */
class LeadManagementTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram('Coding Kids');
    }

    private function makeProgram(string $name): Program
    {
        return Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
    }

    /** Marketing: Management dengan permission fungsi `manage leads`. */
    private function marketing(string $scope = 'all', array $branchIds = []): User
    {
        return $this->makeStaff('management', $scope, $branchIds, ['manage leads']);
    }

    private function makeLead(?Branch $branch, array $overrides = []): Lead
    {
        $parent = $this->makeStaff('parent');
        $parent->update(['name' => 'Ortu '.($overrides['name'] ?? 'Budi'), 'phone' => '0812-0000-0000']);
        $slot = $branch ? TrialSlot::create([
            'branch_id' => $branch->id,
            'program_id' => $overrides['program_id'] ?? $this->program->id,
            'trainer_id' => $this->makeStaff('trainer', 'assigned', [$branch->id])->id,
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
        ]) : null;

        return Lead::create(array_merge([
            'name' => 'Budi',
            'education_level' => 'smp',
            'origin_school' => 'SMP Negeri 5',
            'parent_email' => $parent->email,
            'parent_id' => $parent->id,
            'branch_id' => $branch?->id,
            'program_id' => $this->program->id,
            'trial_slot_id' => $slot?->id,
            'status' => 'trial_scheduled',
        ], $overrides));
    }

    /** Semua nilai sel dari file xlsx hasil export, diratakan jadi satu daftar. */
    private function xlsxCells(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $cells = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                array_push($cells, ...$row->toArray());
            }
        }
        $reader->close();
        unlink($path);

        return $cells;
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/leads')->assertStatus(401);
        $this->getJson('/api/exports/leads')->assertStatus(401);
    }

    public function test_users_without_manage_leads_permission_get_403(): void
    {
        $lead = $this->makeLead($this->bdg);

        foreach (['trainer', 'finance', 'parent', 'management'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/leads')->assertStatus(403);
            $this->putJson("/api/leads/{$lead->id}", ['note' => 'x'])->assertStatus(403);
            $this->get('/api/exports/leads')->assertStatus(403);
        }

        $this->assertNull($lead->fresh()->note);
    }

    public function test_index_lists_leads_with_parent_branch_program_and_slot(): void
    {
        $lead = $this->makeLead($this->bdg, ['name' => 'Budi', 'note' => 'Minta dihubungi sore']);
        Sanctum::actingAs($this->marketing());

        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lead->id)
            ->assertJsonPath('data.0.name', 'Budi')
            ->assertJsonPath('data.0.education_level', 'smp')
            ->assertJsonPath('data.0.status', 'trial_scheduled')
            ->assertJsonPath('data.0.note', 'Minta dihubungi sore')
            ->assertJsonPath('data.0.parent.name', 'Ortu Budi')
            ->assertJsonPath('data.0.parent.phone', '0812-0000-0000')
            ->assertJsonPath('data.0.parent.email', $lead->parent_email)
            ->assertJsonPath('data.0.branch.name', 'Cabang Bandung')
            ->assertJsonPath('data.0.program.name', 'Coding Kids')
            ->assertJsonPath('data.0.trial_slot.program', 'Coding Kids')
            ->assertJsonPath('data.0.trial_slot.branch', 'Cabang Bandung');
    }

    public function test_index_is_newest_first_and_paginated_25_per_page(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->makeLead($this->bdg, ['name' => "Anak {$i}"]);
        }
        Sanctum::actingAs($this->marketing());

        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('data.0.name', 'Anak 26');
    }

    public function test_index_is_limited_to_the_users_branches(): void
    {
        $this->makeLead($this->bdg, ['name' => 'Anak Bandung']);
        $this->makeLead($this->jkt, ['name' => 'Anak Jakarta']);
        $this->makeLead(null, ['name' => 'Anak Lama']); // data demo tanpa cabang

        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));
        $res = $this->getJson('/api/leads')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Anak Bandung', $res->json('data.0.name'));
        // Filter ke cabang di luar cakupan tidak membocorkan apa pun.
        $this->getJson("/api/leads?branch_id={$this->jkt->id}")->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->marketing('all'));
        $this->getJson('/api/leads')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/leads?branch_id={$this->jkt->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_program_and_status(): void
    {
        $robotics = $this->makeProgram('Robotik');
        $this->makeLead($this->bdg, ['name' => 'Anak A']);
        $this->makeLead($this->bdg, ['name' => 'Anak B', 'program_id' => $robotics->id]);
        $this->makeLead($this->bdg, ['name' => 'Anak C', 'status' => 'not_continued']);
        Sanctum::actingAs($this->marketing());

        $res = $this->getJson("/api/leads?program_id={$robotics->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Anak B', $res->json('data.0.name'));

        $res = $this->getJson('/api/leads?status=not_continued')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Anak C', $res->json('data.0.name'));

        $this->getJson('/api/leads?status=trial_scheduled')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/leads?status=ngawur')->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_note_can_be_set_and_cleared_but_nothing_else_changes(): void
    {
        $lead = $this->makeLead($this->bdg, ['name' => 'Budi']);
        Sanctum::actingAs($this->marketing());

        $this->putJson("/api/leads/{$lead->id}", ['note' => 'Tertarik paket bulanan', 'name' => 'Diganti', 'status' => 'not_continued'])
            ->assertOk()
            ->assertJsonPath('data.note', 'Tertarik paket bulanan')
            ->assertJsonPath('data.name', 'Budi');
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'name' => 'Budi', 'status' => 'trial_scheduled', 'note' => 'Tertarik paket bulanan']);

        $this->putJson("/api/leads/{$lead->id}", ['note' => null])->assertOk()->assertJsonPath('data.note', null);
        $this->assertNull($lead->fresh()->note);
    }

    public function test_note_is_limited_to_2000_characters(): void
    {
        $lead = $this->makeLead($this->bdg);
        Sanctum::actingAs($this->marketing());

        $this->putJson("/api/leads/{$lead->id}", ['note' => str_repeat('a', 2001)])
            ->assertStatus(422)->assertJsonValidationErrors('note');
    }

    public function test_lead_of_another_branch_is_404_on_update(): void
    {
        $other = $this->makeLead($this->jkt);
        $legacy = $this->makeLead(null);
        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));

        $this->putJson("/api/leads/{$other->id}", ['note' => 'bocor'])->assertStatus(404);
        $this->putJson("/api/leads/{$legacy->id}", ['note' => 'bocor'])->assertStatus(404);
        $this->assertNull($other->fresh()->note);
        $this->assertNull($legacy->fresh()->note);

        // Staf pusat boleh, termasuk lead lama tanpa cabang.
        Sanctum::actingAs($this->marketing('all'));
        $this->putJson("/api/leads/{$other->id}", ['note' => 'ok'])->assertOk();
        $this->putJson("/api/leads/{$legacy->id}", ['note' => 'ok'])->assertOk();
    }

    public function test_export_returns_an_xlsx_with_only_visible_leads(): void
    {
        $this->makeLead($this->bdg, ['name' => 'Anak Bandung', 'note' => 'Catatan penting']);
        $this->makeLead($this->jkt, ['name' => 'Anak Jakarta']);
        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));

        $response = $this->get('/api/exports/leads')->assertOk()->assertHeader('content-type', self::XLSX);
        $cells = $this->xlsxCells($response);

        $this->assertContains('Anak Bandung', $cells);
        $this->assertContains('Ortu Anak Bandung', $cells);
        $this->assertContains('Catatan penting', $cells);
        $this->assertContains('Cabang Bandung', $cells);
        $this->assertContains('Coding Kids', $cells);
        $this->assertContains('Trial terjadwal', $cells);
        $this->assertNotContains('Anak Jakarta', $cells);
    }

    public function test_export_follows_the_same_filters_as_the_list(): void
    {
        $this->makeLead($this->bdg, ['name' => 'Anak Trial']);
        $this->makeLead($this->bdg, ['name' => 'Anak Leads', 'status' => 'not_continued']);
        Sanctum::actingAs($this->marketing());

        $cells = $this->xlsxCells($this->get('/api/exports/leads?status=not_continued')->assertOk());

        $this->assertContains('Anak Leads', $cells);
        $this->assertContains('Tidak lanjut', $cells);
        $this->assertNotContains('Anak Trial', $cells);
    }
}
