<?php

use App\Http\Controllers\Api\AttendanceImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\FinanceReportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\ExpoReportController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HotIssueController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TrainerAttendanceController;
use App\Http\Controllers\Api\TrialEvaluationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskUpdateController;
use App\Http\Controllers\Api\ProjectExportController;
use App\Http\Controllers\Api\TaskChecklistItemController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\ProjectGanttController;
use App\Http\Controllers\Api\TaskDependencyController;

/*
|--------------------------------------------------------------------------
| API — After Schola Platform (modul Absensi)
|--------------------------------------------------------------------------
| Semua otorisasi ditegakkan di backend (Policy), bukan sekadar di UI.
*/

// --- Publik (tanpa token) ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:5,1');
Route::get('/programs/aktif', [ProgramController::class, 'indexAktif']);

// --- Butuh autentikasi (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    // Sesi & profil
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Sekolah + penugasan trainer
    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store']);
    Route::get('/schools/{school}', [SchoolController::class, 'show']);
    Route::match(['put', 'patch'], '/schools/{school}', [SchoolController::class, 'update']);
    Route::delete('/schools/{school}', [SchoolController::class, 'destroy']);
    Route::post('/schools/{school}/trainers', [SchoolController::class, 'assignTrainers']);

    // Level (classrooms)
    Route::get('/classrooms', [ClassroomController::class, 'index']);
    Route::post('/classrooms', [ClassroomController::class, 'store']);
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show']);
    Route::match(['put', 'patch'], '/classrooms/{classroom}', [ClassroomController::class, 'update']);
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy']);

    // Murid (import didefinisikan sebelum {student})
    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::post('/students/import', [StudentController::class, 'import']);
    Route::get('/students/{student}', [StudentController::class, 'show']);
    Route::match(['put', 'patch'], '/students/{student}', [StudentController::class, 'update']);
    Route::delete('/students/{student}', [StudentController::class, 'destroy']);

    // Pertemuan (class_sessions)
    Route::get('/sessions', [ClassSessionController::class, 'index']);
    Route::post('/sessions', [ClassSessionController::class, 'store']);
    Route::post('/sessions/bulk', [ClassSessionController::class, 'storeBulk']);
    Route::get('/sessions/{classSession}', [ClassSessionController::class, 'show']);
    Route::match(['put', 'patch'], '/sessions/{classSession}', [ClassSessionController::class, 'update']);
    Route::delete('/sessions/{classSession}', [ClassSessionController::class, 'destroy']);
    Route::post('/sessions/{classSession}/start', [ClassSessionController::class, 'start']);
    Route::post('/sessions/{classSession}/lock', [ClassSessionController::class, 'lock']);

    // Absensi murid per pertemuan
    Route::get('/sessions/{classSession}/student-attendances', [StudentAttendanceController::class, 'index']);
    Route::put('/sessions/{classSession}/student-attendances', [StudentAttendanceController::class, 'sync']);

    // Absensi trainer per pertemuan
    Route::get('/sessions/{classSession}/trainer-attendances', [TrainerAttendanceController::class, 'index']);
    Route::post('/sessions/{classSession}/trainer-attendances', [TrainerAttendanceController::class, 'checkIn']);

    // Rekap absensi murid (JSON untuk tampilan layar) + export Excel & PDF
    Route::get('/exports/attendance/recap', [ExportController::class, 'attendanceRecap']);
    Route::get('/exports/attendance/excel', [ExportController::class, 'attendanceExcel']);
    Route::get('/exports/attendance/pdf', [ExportController::class, 'attendancePdf']);
    // Export laporan ekspo (Excel & PDF dengan foto)
    Route::get('/exports/expo/excel', [ExportController::class, 'expoExcel']);
    Route::get('/exports/expo/pdf', [ExportController::class, 'expoPdf']);

    // Import absensi dari Excel/CSV — pratinjau dulu sebelum disimpan
    Route::post('/attendance-imports/preview', [AttendanceImportController::class, 'preview']);
    Route::post('/attendance-imports/{token}/commit', [AttendanceImportController::class, 'commit']);

    // Laporan Ekspo / Free-Trial
    Route::get('/expo-reports', [ExpoReportController::class, 'index']);
    Route::post('/expo-reports', [ExpoReportController::class, 'store']);
    Route::get('/expo-reports/{expoReport}', [ExpoReportController::class, 'show']);
    Route::match(['put', 'patch'], '/expo-reports/{expoReport}', [ExpoReportController::class, 'update']);
    Route::delete('/expo-reports/{expoReport}', [ExpoReportController::class, 'destroy']);

    // Evaluasi Trial (Modul 3) — trainer mengisi, Management melihat semua
    Route::get('/trial-evaluations', [TrialEvaluationController::class, 'index']);
    Route::post('/trial-evaluations', [TrialEvaluationController::class, 'store']);
    Route::get('/trial-evaluations/{trialEvaluation}', [TrialEvaluationController::class, 'show']);
    Route::match(['put', 'patch'], '/trial-evaluations/{trialEvaluation}', [TrialEvaluationController::class, 'update']);
    Route::delete('/trial-evaluations/{trialEvaluation}', [TrialEvaluationController::class, 'destroy']);

    // Keputusan lanjut/tidak + Pendaftaran Resmi (Modul 4) — hanya Management
    Route::middleware('permission:manage enrollment')->group(function () {
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::put('/trial-evaluations/{trialEvaluation}/decision', [EnrollmentController::class, 'saveDecision']);
        Route::put('/trial-evaluations/{trialEvaluation}/registration', [EnrollmentController::class, 'saveRegistration']);
        Route::post('/trial-evaluations/{trialEvaluation}/registration/submit', [EnrollmentController::class, 'submitRegistration']);
    });

    // Verifikasi & Pembayaran (Modul 5)
    Route::middleware('permission:verify registrations')->group(function () {
        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::post('/registrations/{registration}/approve', [RegistrationController::class, 'approve']);
        Route::post('/registrations/{registration}/request-revision', [RegistrationController::class, 'requestRevision']);
    });
    Route::middleware('permission:manage invoices')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void']);
    });
    Route::middleware('permission:confirm payments')->group(function () {
        Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject']);
    });
    Route::get('/finance/aging', [FinanceReportController::class, 'aging'])->middleware('permission:view finance reports');

    // Kelola user & role — hanya Management
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // Hot Issue — popup notifikasi yang tampil setelah login di semua role dashboard.
    Route::get('/hot-issues/active', [HotIssueController::class, 'active']);
    Route::post('/hot-issues/{hotIssue}/dismiss', [HotIssueController::class, 'dismiss']);
    Route::middleware('permission:manage hot issues')->group(function () {
        Route::get('/hot-issues', [HotIssueController::class, 'index']);
        Route::post('/hot-issues', [HotIssueController::class, 'store']);
        Route::match(['put', 'patch'], '/hot-issues/{hotIssue}', [HotIssueController::class, 'update']);
        Route::delete('/hot-issues/{hotIssue}', [HotIssueController::class, 'destroy']);
    });
	
	Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
	
	// Manajemen Proyek (Modul 12) — Management lihat semua, Developer hanya team-nya (detail di Policy).
    Route::middleware('role:management|developer')->group(function () {
		Route::apiResource('teams', TeamController::class);
        Route::apiResource('projects', ProjectController::class);
		Route::apiResource('tasks', TaskController::class);
		Route::apiResource('tasks.updates', TaskUpdateController::class)->only(['index', 'store']);
		Route::apiResource('tasks.checklist', TaskChecklistItemController::class)->shallow()->except(['show']);
        Route::get('projects/{project}/export', ProjectExportController::class);
		Route::apiResource('tasks.attachments', TaskAttachmentController::class)->shallow()->only(['index', 'store', 'destroy']);
        Route::get('attachments/{attachment}/download', [TaskAttachmentController::class, 'download']);
		Route::apiResource('tasks.dependencies', TaskDependencyController::class)->shallow()->only(['index', 'store', 'destroy']);
        Route::get('projects/{project}/gantt', ProjectGanttController::class);
    });

    // Khusus Management - wajib login & role tertentu
    Route::middleware(['auth:sanctum', 'role:management'])->group(function () {
    Route::apiResource('programs', ProgramController::class);
    Route::post('/programs/{program}/jadwal-trial', [ProgramController::class, 'storeJadwalTrial']);
    Route::delete('/programs/{program}/jadwal-trial/{jadwal}', [ProgramController::class, 'destroyJadwalTrial']);
    });
    
});
