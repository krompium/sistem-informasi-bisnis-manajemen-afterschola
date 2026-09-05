<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .kop .sub { font-size: 11px; margin-top: 2px; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 3px 4px; }
        th { background: #eee; text-align: center; font-size: 9px; }
        td.c { text-align: center; }
        td.hadir { color: #0a7d24; font-weight: bold; }
        td.tidak { color: #b00; }
        tr.total td { background: #f4f4f4; font-weight: bold; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Rekap Absensi Murid — {{ $recap->school->name }}</div>
        <div class="sub muted">Periode: {{ $recap->periodLabel() }}</div>
    </div>

    @forelse ($recap->groups as $group)
        @php($sessions = $group['sessions'])
        <h2>Level: {{ $group['classroom']->name }}</h2>

        @if ($sessions->isEmpty())
            <p class="muted">Belum ada pertemuan pada periode ini.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:24px">NO</th>
                        <th style="text-align:left">NAMA</th>
                        <th style="width:50px">KELAS</th>
                        @foreach ($sessions as $session)
                            <th>P{{ $session->meeting_no }}<br>{{ $session->date->format('d/m') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['students'] as $i => $student)
                        <tr>
                            <td class="c">{{ $i + 1 }}</td>
                            <td>{{ $student->name }}</td>
                            <td class="c">{{ $student->origin_class }}</td>
                            @foreach ($sessions as $session)
                                @php($present = $group['present'][$session->id][$student->id] ?? false)
                                <td class="c {{ $present ? 'hadir' : 'tidak' }}">{{ $present ? 'H' : '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="3">JUMLAH SISWA YANG HADIR</td>
                        @foreach ($sessions as $session)
                            <td class="c">{{ $group['present_totals'][$session->id] ?? 0 }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
    @empty
        <p class="muted">Tidak ada level pada sekolah ini.</p>
    @endforelse
</body>
</html>
