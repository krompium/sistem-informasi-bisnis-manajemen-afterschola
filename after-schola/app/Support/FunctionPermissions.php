<?php

namespace App\Support;

/**
 * Permission per fungsi kerja di dalam role Management/Keuangan (PRD 3.2).
 * Tidak dimiliki lewat role Management: diberikan per user (MGT-02).
 */
class FunctionPermissions
{
    public const ALL = [
        // Marketing
        'manage programs', 'manage trial slots', 'manage leads',
        // Admin pendaftaran
        'verify registrations',
        // Koordinator program
        'manage classes', 'manage schedule', 'assign trainers', 'review reports',
        // Pimpinan
        'view dashboard', 'view all attendance', 'view finance reports',
        // Admin sistem
        'manage users', 'manage branches', 'manage integrations',
        // Keuangan
        'confirm payments', 'manage invoices', 'export trainer attendance',
    ];
}
