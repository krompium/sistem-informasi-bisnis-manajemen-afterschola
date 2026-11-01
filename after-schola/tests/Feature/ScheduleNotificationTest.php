<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Student;
use App\Models\TrainerAssignment;
use App\Models\User;
use App\Notifications\ClassSessionScheduleNotification;
use App\Notifications\StudentPlacedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 6d (MGT-37, SYS-03): notifikasi jadwal ke trainer dan orang tua saat sesi dibuat atau berubah,
 * dan ke orang tua saat siswa ditempatkan. Kanal: in-app (database) lebih dulu, lalu email.
 */
class ScheduleNotificationTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private ClassGroup $class;

    private User $trainer;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgram();
        $level = $this->program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        $this->class = ClassGroup::create([
            'program_id' => $this->program->id, 'level_id' => $level->id, 'branch_id' => $this->bdg->id,
            'name' => 'Coding A', 'capacity' => 10,
        ]);
        $this->trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->trainer->id, 'role' => 'utama']);
        $this->staff = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage schedule', 'manage classes']);
        Sanctum::actingAs($this->staff);
    }

    /** Orang tua dengan satu anak di kelas (status enrollment sesuai $status). */
    private function parentWithChild(ClassGroup $class, string $status = 'aktif', string $childName = 'Anak'): User
    {
        $parent = $this->makeStaff('parent');
        $student = Student::create(['name' => $childName, 'branch_id' => $this->bdg->id, 'parent_id' => $parent->id]);
        Enrollment::create(['class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => '2026-10-01', 'status' => $status]);

        return $parent;
    }

    private function sessionPayload(array $overrides = []): array
    {
        return $overrides + [
            'class_group_id' => $this->class->id, 'trainer_id' => $this->trainer->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite',
        ];
    }

    private function existingSession(array $overrides = []): ClassSession
    {
        return ClassSession::create($overrides + [
            'class_group_id' => $this->class->id, 'trainer_id' => $this->trainer->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite',
        ]);
    }

    private function sessionKind(User $user, string $kind): \Closure
    {
        return fn (ClassSessionScheduleNotification $n, array $channels) => $channels === ['database', 'mail']
            && $n->toArray($user)['kind'] === 'session_'.$kind;
    }

    public function test_new_session_notifies_the_trainer_and_parents_of_active_enrollees_only(): void
    {
        Notification::fake();
        $parent = $this->parentWithChild($this->class, 'aktif', 'Budi');
        $endedParent = $this->parentWithChild($this->class, 'selesai');
        $otherClass = ClassGroup::create(['program_id' => $this->program->id, 'level_id' => $this->class->level_id, 'branch_id' => $this->bdg->id, 'name' => 'Kelas Lain', 'capacity' => 5]);
        $otherParent = $this->parentWithChild($otherClass);
        Student::create(['name' => 'Tanpa Akun Ortu', 'branch_id' => $this->bdg->id]); // tidak boleh membuat error

        $this->postJson('/api/class-sessions', $this->sessionPayload())->assertCreated();

        Notification::assertSentTo($this->trainer, ClassSessionScheduleNotification::class, $this->sessionKind($this->trainer, 'scheduled'));
        Notification::assertSentTo($parent, ClassSessionScheduleNotification::class, function ($n, $channels) use ($parent) {
            $message = $n->toArray($parent)['message'];

            return $channels === ['database', 'mail']
                && str_contains($message, 'Coding A')
                && str_contains($message, '09:00–10:30')
                && str_contains($message, '10/11/2026')
                && str_contains($message, 'Budi');
        });
        Notification::assertNotSentTo($endedParent, ClassSessionScheduleNotification::class);
        Notification::assertNotSentTo($otherParent, ClassSessionScheduleNotification::class);
        Notification::assertNotSentTo($this->staff, ClassSessionScheduleNotification::class);
    }

    public function test_class_without_enrollees_only_notifies_the_trainer(): void
    {
        Notification::fake();

        $this->postJson('/api/class-sessions', $this->sessionPayload())->assertCreated();

        Notification::assertSentTimes(ClassSessionScheduleNotification::class, 1);
        Notification::assertSentTo($this->trainer, ClassSessionScheduleNotification::class);
    }

    public function test_changing_date_time_or_mode_notifies_but_other_edits_do_not(): void
    {
        $parent = $this->parentWithChild($this->class);
        $session = $this->existingSession();

        Notification::fake();
        $this->putJson("/api/class-sessions/{$session->id}", $this->sessionPayload(['start_time' => '10:00', 'end_time' => '11:30']))->assertOk();
        Notification::assertSentTo($this->trainer, ClassSessionScheduleNotification::class, $this->sessionKind($this->trainer, 'changed'));
        Notification::assertSentTo($parent, ClassSessionScheduleNotification::class, $this->sessionKind($parent, 'changed'));

        // Mengirim ulang nilai yang sama, atau hanya mengubah catatan dan nomor pertemuan, bukan perubahan jadwal.
        Notification::fake();
        $this->putJson("/api/class-sessions/{$session->id}", $this->sessionPayload(['start_time' => '10:00', 'end_time' => '11:30']))->assertOk();
        $this->putJson("/api/class-sessions/{$session->id}", $this->sessionPayload(['start_time' => '10:00', 'end_time' => '11:30', 'meeting_no' => 7, 'note' => 'Bawa laptop']))->assertOk();
        Notification::assertNothingSent();

        Notification::fake();
        $this->putJson("/api/class-sessions/{$session->id}", $this->sessionPayload(['start_time' => '10:00', 'end_time' => '11:30', 'meeting_no' => 7, 'mode' => 'online']))->assertOk();
        Notification::assertSentTo($parent, ClassSessionScheduleNotification::class, $this->sessionKind($parent, 'changed'));
    }

    public function test_changing_the_trainer_notifies_new_trainer_old_trainer_and_parents(): void
    {
        $parent = $this->parentWithChild($this->class);
        $session = $this->existingSession();
        $substitute = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);

        Notification::fake();
        $this->putJson("/api/class-sessions/{$session->id}", $this->sessionPayload(['trainer_id' => $substitute->id]))->assertOk();

        Notification::assertSentTo($substitute, ClassSessionScheduleNotification::class, $this->sessionKind($substitute, 'changed'));
        Notification::assertSentTo($this->trainer, ClassSessionScheduleNotification::class, function ($n, $channels) {
            $data = $n->toArray($this->trainer);

            return $channels === ['database', 'mail'] && $data['kind'] === 'session_released' && str_contains($data['message'], 'tidak lagi bertugas');
        });
        Notification::assertSentTo($parent, ClassSessionScheduleNotification::class, fn ($n) => str_contains($n->toArray($parent)['message'], $substitute->name));
    }

    public function test_placement_notifies_the_parent_with_class_and_upcoming_sessions(): void
    {
        $parent = $this->makeStaff('parent');
        $lead = Lead::create([
            'name' => 'Sari', 'education_level' => 'smp', 'origin_school' => 'SMP 5', 'parent_email' => $parent->email,
            'parent_id' => $parent->id, 'program_id' => $this->program->id, 'status' => 'trial_scheduled',
        ]);
        $registration = $this->makeRegistration(
            ['status' => 'terverifikasi', 'branch_id' => $this->bdg->id, 'lead_id' => $lead->id, 'student_name' => 'Sari'],
            $this->program
        );
        $this->makeInvoice($registration, ['status' => 'paid', 'paid_at' => now()]);
        $tomorrow = now(config('app.display_timezone'))->addDay()->toDateString();
        $this->existingSession(['date' => $tomorrow, 'start_time' => '14:00', 'end_time' => '15:00']);
        $staff = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']);
        Sanctum::actingAs($staff);

        Notification::fake();
        $this->postJson('/api/enrollments', ['registration_id' => $registration->id, 'class_group_id' => $this->class->id])->assertCreated();

        Notification::assertSentTo($parent, StudentPlacedNotification::class, function ($n, $channels) use ($parent, $tomorrow) {
            $data = $n->toArray($parent);

            return $channels === ['database', 'mail']
                && $data['kind'] === 'student_placed'
                && str_contains($data['message'], 'Sari')
                && str_contains($data['message'], 'Coding A')
                && str_contains(implode(' ', $n->toMail($parent)->introLines), '14:00–15:00')
                && str_contains(implode(' ', $n->toMail($parent)->introLines), \Illuminate\Support\Carbon::parse($tomorrow)->format('d/m/Y'));
        });
        Notification::assertSentTimes(StudentPlacedNotification::class, 1);
    }

    public function test_placement_of_a_student_without_parent_account_still_succeeds_quietly(): void
    {
        $registration = $this->makeRegistration(['status' => 'terverifikasi', 'branch_id' => $this->bdg->id], $this->program);
        $this->makeInvoice($registration, ['status' => 'paid', 'paid_at' => now()]);
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']));

        Notification::fake();
        $this->postJson('/api/enrollments', ['registration_id' => $registration->id, 'class_group_id' => $this->class->id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_delivery_failure_never_breaks_the_request_or_other_recipients(): void
    {
        $broken = $this->parentWithChild($this->class, 'aktif', 'Rusak');
        $healthy = $this->parentWithChild($this->class, 'aktif', 'Sehat');
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($broken) {
            if ($event->notifiable->is($broken)) {
                throw new \RuntimeException('smtp down');
            }
        });

        $this->postJson('/api/class-sessions', $this->sessionPayload())->assertCreated();

        $this->assertSame(1, $healthy->notifications()->count());
        $this->assertSame(1, $this->trainer->notifications()->count());
        $this->assertSame(0, $broken->notifications()->count());
        $this->assertDatabaseCount('class_sessions', 1);
    }
}
