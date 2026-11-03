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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 7b (TRN-07, BR-15, AC-09, D-07): check-in trainer pada sesi reguler.
 * Onsite: foto + GPS. Online: screenshot, GPS ditolak. Satu kali per sesi, sampai 4 jam setelah sesi berakhir (WIB).
 * Hanya pelaksana sesi (termasuk pengganti) yang boleh check-in.
 */
class RegularTrainerCheckInTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $class;

    private User $main;

    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $program = Program::create(['name' => 'Coding Kids', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        $this->class = ClassGroup::create(['program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $this->bdg->id, 'name' => 'Coding A', 'capacity' => 20]);
        $this->main = $this->trainer($this->bdg);
        $this->assistant = $this->trainer($this->bdg);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->main->id, 'role' => 'utama']);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->assistant->id, 'role' => 'asisten']);
        $this->at('2026-11-10 09:30'); // sesi 09:00–10:30 WIB, sedang berlangsung
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $jakarta): void
    {
        Carbon::setTestNow(Carbon::parse($jakarta, 'Asia/Jakarta')->utc()); // now() produksi berzona UTC
    }

    private function trainer(Branch $branch): User
    {
        return $this->makeStaff('trainer', 'assigned', [$branch->id]);
    }

    private function sessionFor(User $trainer, string $mode = 'onsite', string $date = '2026-11-10'): ClassSession
    {
        static $meeting = 0;

        return ClassSession::create([
            'class_group_id' => $this->class->id, 'trainer_id' => $trainer->id, 'date' => $date,
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => ++$meeting, 'mode' => $mode,
        ]);
    }

    private function url(ClassSession $session): string
    {
        return "/api/class-sessions/{$session->id}/trainer-attendance";
    }

    /** Berkas dengan isi biner gambar sungguhan (build PHP sandbox tanpa GD, jadi tidak memakai fake()->image()). */
    private function file(string $kind = 'png', string $name = 'foto-asli-klien.png', int $padKb = 0): UploadedFile
    {
        $bytes = [
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
            'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
            'gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            'pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n",
        ][$kind];

        return UploadedFile::fake()->createWithContent($name, $bytes.str_repeat("\0", $padKb * 1024));
    }

    private function onsite(array $overrides = []): array
    {
        return $overrides + ['status' => 'hadir', 'photo' => $this->file(), 'latitude' => -6.9175, 'longitude' => 107.6191, 'note' => 'Lancar'];
    }

    public function test_pelaksana_checks_in_onsite_with_photo_and_gps_and_the_file_is_stored_safely(): void
    {
        $session = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);

        $res = $this->post($this->url($session), $this->onsite(), ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.status', 'hadir')
            ->assertJsonPath('data.trainer_id', $this->main->id)
            ->assertJsonPath('data.class_session_id', $session->id);

        $attendance = TrainerAttendance::firstOrFail();
        $this->assertSame('2026-11-10 09:30', $attendance->check_in_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertEquals(-6.9175, $attendance->latitude);
        $this->assertEquals(107.6191, $attendance->longitude);
        $this->assertSame('Lancar', $attendance->note);

        // Path relatif di database, nama UUID (bukan nama dari klien), tersimpan di disk public.
        $this->assertMatchesRegularExpression('#^trainer-attendances/[0-9a-f-]{36}\.png$#', $attendance->photo_path);
        $this->assertStringNotContainsString('foto-asli-klien', $attendance->photo_path);
        Storage::disk('public')->assertExists($attendance->photo_path);
        $this->assertStringContainsString($attendance->photo_path, $res->json('data.photo_url'));
    }

    public function test_online_requires_a_screenshot_and_rejects_gps(): void
    {
        $session = $this->sessionFor($this->main, 'online');
        Sanctum::actingAs($this->main);

        $this->post($this->url($session), ['status' => 'hadir'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->post($this->url($session), ['status' => 'hadir', 'photo' => $this->file(), 'latitude' => -6.9, 'longitude' => 107.6], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->assertSame(0, TrainerAttendance::count());

        $this->post($this->url($session), ['status' => 'terlambat', 'photo' => $this->file('jpg', 'ss.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $attendance = TrainerAttendance::firstOrFail();
        $this->assertNull($attendance->latitude);
        $this->assertNull($attendance->longitude);
        $this->assertSame('terlambat', $attendance->status);
    }

    public function test_onsite_requires_photo_and_both_coordinates_within_range(): void
    {
        $session = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);
        $post = fn (array $data) => $this->post($this->url($session), $data, ['Accept' => 'application/json']);

        $post(['status' => 'hadir'])->assertStatus(422)->assertJsonValidationErrors(['photo', 'latitude', 'longitude']);
        $post($this->onsite(['latitude' => null]))->assertStatus(422)->assertJsonValidationErrors('latitude');
        $post($this->onsite(['longitude' => null]))->assertStatus(422)->assertJsonValidationErrors('longitude');
        $post($this->onsite(['latitude' => 91]))->assertStatus(422)->assertJsonValidationErrors('latitude');
        $post($this->onsite(['longitude' => 181]))->assertStatus(422)->assertJsonValidationErrors('longitude');
        $post($this->onsite(['status' => 'izin']))->assertStatus(422)->assertJsonValidationErrors('status');
        $post($this->onsite(['status' => null]))->assertStatus(422)->assertJsonValidationErrors('status');
        $post($this->onsite(['note' => str_repeat('x', 256)]))->assertStatus(422)->assertJsonValidationErrors('note');

        $this->assertSame(0, TrainerAttendance::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_accepts_only_jpeg_or_png_up_to_five_megabytes(): void
    {
        $session = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);
        $post = fn (UploadedFile $photo) => $this->post($this->url($session), $this->onsite(['photo' => $photo]), ['Accept' => 'application/json']);

        $post($this->file('gif', 'anim.gif'))->assertStatus(422)->assertJsonValidationErrors('photo');
        $post($this->file('pdf', 'bukti.pdf'))->assertStatus(422)->assertJsonValidationErrors('photo');
        $post($this->file('png', 'besar.png', padKb: 5200))->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->assertSame([], Storage::disk('public')->allFiles());

        $post($this->file('jpg', 'ok.jpg'))->assertCreated();
        $this->assertMatchesRegularExpression('#\.jpg$#', TrainerAttendance::firstOrFail()->photo_path);
    }

    public function test_absent_status_needs_no_proof_on_onsite_but_still_rejects_gps_online(): void
    {
        $onsite = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);
        $this->post($this->url($onsite), ['status' => 'tidak_hadir', 'note' => 'Sakit'], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.photo_url', null);

        $online = $this->sessionFor($this->main, 'online');
        $this->post($this->url($online), ['status' => 'tidak_hadir', 'latitude' => -6.9, 'longitude' => 107.6], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->post($this->url($online), ['status' => 'tidak_hadir'], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(2, TrainerAttendance::where('status', 'tidak_hadir')->count());
    }

    public function test_only_one_check_in_per_session(): void
    {
        $session = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);

        $this->post($this->url($session), $this->onsite(), ['Accept' => 'application/json'])->assertCreated();
        $this->post($this->url($session), $this->onsite(['status' => 'terlambat']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('check_in');

        $this->assertSame(1, TrainerAttendance::count());
        $this->assertSame('hadir', TrainerAttendance::firstOrFail()->status);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_a_concurrent_duplicate_is_a_validation_error_and_leaves_no_orphan_file(): void
    {
        $session = $this->sessionFor($this->main);
        Sanctum::actingAs($this->main);
        // Dua permintaan bersamaan (mis. tombol ditekan dua kali): baris lain muncul tepat sebelum insert kita.
        TrainerAttendance::creating(function () use ($session) {
            DB::table('trainer_attendances')->insert([
                'class_session_id' => $session->id, 'trainer_id' => $this->main->id, 'check_in_at' => now(),
                'status' => 'hadir', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->post($this->url($session), $this->onsite(), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('check_in');

        $this->assertSame(1, TrainerAttendance::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_check_in_window_opens_on_the_session_date_and_closes_four_hours_after_it_ends_in_jakarta_time(): void
    {
        $post = fn (ClassSession $s) => $this->post($this->url($s), $this->onsite(), ['Accept' => 'application/json']);
        Sanctum::actingAs($this->main);

        $this->at('2026-11-09 23:59');                        // sehari sebelum sesi (UTC masih 9 Nov 16:59)
        $post($this->sessionFor($this->main))->assertStatus(422)->assertJsonValidationErrors('check_in');

        $this->at('2026-11-10 00:30');                        // hari sesi di Jakarta (UTC masih 9 Nov 17:30): dibuka
        $post($this->sessionFor($this->main))->assertCreated();

        $this->at('2026-11-10 14:30');                        // tepat 4 jam setelah selesai 10:30: masih boleh
        $post($this->sessionFor($this->main))->assertCreated();

        $this->at('2026-11-10 14:31');                        // lewat batas
        $late = $this->sessionFor($this->main);
        $post($late)->assertStatus(422)->assertJsonValidationErrors('check_in');
        $this->assertSame(2, TrainerAttendance::count());
        $this->assertDatabaseMissing('trainer_attendances', ['class_session_id' => $late->id]);
    }

    public function test_only_the_pelaksana_may_check_in_including_a_substitute(): void
    {
        $substitute = $this->trainer($this->bdg); // tidak ditugaskan ke kelas
        $session = $this->sessionFor($substitute);

        Sanctum::actingAs($substitute);
        $this->post($this->url($session), $this->onsite(), ['Accept' => 'application/json'])->assertCreated();

        // Trainer penugasan yang bukan pelaksana: 403. Trainer tak terkait: 404. Pembaca dan orang tua: 403.
        $other = $this->sessionFor($this->main);
        $denied = [
            [$this->assistant, 403], [$this->trainer($this->bdg), 404], [$this->trainer($this->jkt), 404],
            [$this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage schedule']), 403],
            [$this->makeStaff('management', 'assigned', [$this->bdg->id], ['view all attendance']), 403],
            [$this->makeStaff('management', 'assigned', [$this->jkt->id], ['manage schedule']), 404],
            [$this->makeStaff('parent'), 403],
        ];
        foreach ($denied as [$user, $status]) {
            Sanctum::actingAs($user);
            $this->post($this->url($other), $this->onsite(), ['Accept' => 'application/json'])->assertStatus($status);
        }

        $this->assertSame(1, TrainerAttendance::count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_trainer_can_read_only_their_own_check_in(): void
    {
        $session = $this->sessionFor($this->main);

        Sanctum::actingAs($this->main);
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('data', null);
        $this->post($this->url($session), $this->onsite(), ['Accept' => 'application/json'])->assertCreated();
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('data.status', 'hadir')->assertJsonPath('data.trainer_id', $this->main->id);

        // Trainer penugasan lain di kelas yang sama tidak melihat check-in orang lain.
        Sanctum::actingAs($this->assistant);
        $this->getJson($this->url($session))->assertOk()->assertJsonPath('data', null);

        Sanctum::actingAs($this->trainer($this->bdg));
        $this->getJson($this->url($session))->assertNotFound();
    }
}
