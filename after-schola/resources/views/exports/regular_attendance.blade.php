<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #0B2A5B; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; color: #0B2A5B; }
        .kop .sub { font-size: 11px; margin-top: 2px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0B2A5B; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; }
        .r { text-align: right; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Rekap Absensi Siswa, {{ \Illuminate\Support\Carbon::parse($report->from)->format('d/m/Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($report->to)->format('d/m/Y') }}</div>
        <div class="sub">{{ $recap['sessions'] }} sesi tercatat</div>
    </div>

    <table>
        <tr><th>Kelas</th><th>Siswa</th><th class="r">Pertemuan</th><th class="r">Hadir</th><th class="r">Tidak Hadir</th><th class="r">Kehadiran</th></tr>
        @forelse ($recap['rows'] as $row)
            <tr>
                <td>{{ $row['class_group']['name'] }}</td>
                <td>{{ $row['student']['name'] }}</td>
                <td class="r">{{ $row['sessions'] }}</td>
                <td class="r">{{ $row['present'] }}</td>
                <td class="r">{{ $row['absent'] }}</td>
                <td class="r">{{ rtrim(rtrim(number_format($row['percentage'], 1, ',', '.'), '0'), ',') }}%</td>
            </tr>
        @empty
            <tr><td colspan="6">Tidak ada data pada periode ini.</td></tr>
        @endforelse
    </table>
</body>
</html>
