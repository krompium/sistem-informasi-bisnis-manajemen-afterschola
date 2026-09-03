<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpoReportController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TrainerAttendanceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/sessions/{classSession}', [ClassSessionController::class, 'show']);
    Route::match(['put', 'patch'], '/sessions/{classSession}', [ClassSessionController::class, 'update']);
    Route::delete('/sessions/{classSession}', [ClassSessionController::class, 'destroy']);
    Route::post('/sessions/{classSession}/lock', [ClassSessionController::class, 'lock']);

    // Absensi murid per pertemuan
    Route::get('/sessions/{classSession}/student-attendances', [StudentAttendanceController::class, 'index']);
    Route::put('/sessions/{classSession}/student-attendances', [StudentAttendanceController::class, 'sync']);

    // Absensi trainer per pertemuan
    Route::get('/sessions/{classSession}/trainer-attendances', [TrainerAttendanceController::class, 'index']);
    Route::post('/sessions/{classSession}/trainer-attendances', [TrainerAttendanceController::class, 'checkIn']);

    // Export rekap absensi (Excel & PDF) — hanya sekolah yang boleh diakses
    Route::get('/exports/attendance/excel', [ExportController::class, 'attendanceExcel']);
    Route::get('/exports/attendance/pdf', [ExportController::class, 'attendancePdf']);

    // Laporan Ekspo / Free-Trial
    Route::get('/expo-reports', [ExpoReportController::class, 'index']);
    Route::post('/expo-reports', [ExpoReportController::class, 'store']);
    Route::get('/expo-reports/{expoReport}', [ExpoReportController::class, 'show']);
    Route::match(['put', 'patch'], '/expo-reports/{expoReport}', [ExpoReportController::class, 'update']);
    Route::delete('/expo-reports/{expoReport}', [ExpoReportController::class, 'destroy']);

    // Kelola user & role — hanya Management
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
