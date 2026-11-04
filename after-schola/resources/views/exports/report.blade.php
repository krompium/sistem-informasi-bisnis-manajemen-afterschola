<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 18px; margin: 0; }
        .kop .sub { font-size: 11px; margin-top: 2px; }
        h2 { font-size: 14px; text-align: center; margin: 0 0 12px; }
        table.rows { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.rows td { padding: 3px 2px; vertical-align: top; }
        table.rows td.label { width: 28%; color: #555; }
        table.scores { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.scores th, table.scores td { border: 1px solid #111; padding: 5px 6px; text-align: left; }
        table.scores td.num, table.scores th.num { text-align: center; width: 14%; }
        h3 { font-size: 12px; margin: 0 0 4px; }
        .summary { border: 1px solid #111; padding: 8px; }
        .muted { color: #666; font-size: 9px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        @if ($branch)
            <div class="sub">{{ $branch->name }}</div>
        @endif
    </div>

    <h2>LAPORAN PERKEMBANGAN SISWA</h2>

    <table class="rows">
        <tr><td class="label">Nama siswa</td><td>{{ $evaluation->enrollment->student->name }}</td></tr>
        <tr><td class="label">Program</td><td>{{ $evaluation->enrollment->classGroup->program->name }}</td></tr>
        <tr><td class="label">Kelas</td><td>{{ $evaluation->enrollment->classGroup->name }}</td></tr>
        <tr><td class="label">Periode</td><td>{{ $evaluation->period }}</td></tr>
        <tr><td class="label">Trainer</td><td>{{ $evaluation->evaluator?->name ?? '-' }}</td></tr>
        <tr><td class="label">Terbit</td><td>{{ $evaluation->report?->sent_at?->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td></tr>
    </table>

    @php($scores = $evaluation->criteria->pluck('pivot.score', 'id'))
    <table class="scores">
        <tr><th>Kriteria</th><th class="num">Skor</th><th class="num">Maks.</th></tr>
        @foreach ($evaluation->enrollment->classGroup->program->rubricCriteria as $criterion)
            <tr>
                <td>{{ $criterion->name }}</td>
                <td class="num">{{ $scores->get($criterion->id) ?? '-' }}</td>
                <td class="num">{{ $criterion->max_score }}</td>
            </tr>
        @endforeach
    </table>

    <h3>Ringkasan</h3>
    <div class="summary">{{ $evaluation->summary }}</div>

    <div class="muted">Laporan ini dibuat otomatis oleh sistem After Schola setelah ditinjau koordinator program.</div>
</body>
</html>
