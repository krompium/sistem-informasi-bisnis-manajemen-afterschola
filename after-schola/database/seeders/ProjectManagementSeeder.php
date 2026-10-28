<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Data demo Modul 12. Jalankan: php artisan db:seed --class=ProjectManagementSeeder
// Aman dijalankan berulang (firstOrCreate). Password semua akun: "password".
class ProjectManagementSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $andi = $this->developer('andi@afterschola.id', 'Andi Developer');
        $budi = $this->developer('budi@afterschola.id', 'Budi Developer');
        $citra = $this->developer('citra@afterschola.id', 'Citra Developer');

        $backend = Team::firstOrCreate(['name' => 'Tim Backend'], ['description' => 'API dan database']);
        $backend->members()->syncWithoutDetaching([
            $andi->id => ['role_in_team' => 'lead'],
            $budi->id => ['role_in_team' => 'member'],
        ]);

        $frontend = Team::firstOrCreate(['name' => 'Tim Frontend'], ['description' => 'Antarmuka web']);
        $frontend->members()->syncWithoutDetaching([
            $citra->id => ['role_in_team' => 'lead'],
        ]);

        $absensi = $this->project($backend, 'API Absensi', 'ongoing');
        $this->project($backend, 'Modul Laporan', 'planning');
        $board = $this->project($frontend, 'Board Kanban', 'ongoing');

        // [proyek, judul, assignee, status, progres, prioritas, tenggat (hari dari sekarang)]
        $tasks = [
            [$absensi, 'Rancang skema database', $andi, 'done', 100, 'high', -5],
            [$absensi, 'Endpoint check-in trainer', $budi, 'in_progress', 60, 'high', 3],
            [$absensi, 'Validasi upload foto', $budi, 'review', 90, 'medium', 5],
            [$absensi, 'Export rekap Excel', $andi, 'todo', 0, 'low', 10],
            [$board, 'Layout board 4 kolom', $citra, 'in_progress', 40, 'medium', -2],
            [$board, 'Form task', $citra, 'todo', 0, 'medium', 7],
        ];

        foreach ($tasks as [$project, $title, $assignee, $status, $progress, $priority, $dueInDays]) {
            Task::firstOrCreate(
                ['project_id' => $project->id, 'title' => $title],
                [
                    'assignee_id' => $assignee->id,
                    'status' => $status,
                    'progress' => $progress,
                    'priority' => $priority,
                    'due_date' => now()->addDays($dueInDays)->toDateString(),
                ],
            );
        }
    }

    private function developer(string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password'), 'is_active' => true],
        );
        $user->syncRoles('developer');

        return $user;
    }

    private function project(Team $team, string $name, string $status): Project
    {
        return Project::firstOrCreate(
            ['team_id' => $team->id, 'name' => $name],
            [
                'description' => "Proyek demo {$name}",
                'status' => $status,
                'start_date' => now()->subWeeks(2)->toDateString(),
                'due_date' => now()->addWeeks(4)->toDateString(),
            ],
        );
    }
}
