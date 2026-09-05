<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #0B2A5B; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 16px; margin: 0; color: #0B2A5B; }
        .kop .sub { font-size: 11px; margin-top: 2px; color: #555; }
        .card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; page-break-inside: avoid; }
        .card h2 { font-size: 13px; margin: 0 0 2px; color: #0B2A5B; }
        .meta { color: #555; font-size: 10px; margin-bottom: 6px; }
        table.f { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.f td { padding: 3px 4px; vertical-align: top; }
        table.f td.k { width: 130px; color: #555; }
        .stars { color: #f59e0b; font-size: 13px; }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .ok { background: #D1FAE5; color: #065F46; }
        .warn { background: #FEF3C7; color: #92400E; }
        .bad { background: #FEE2E2; color: #991B1B; }
        .photos { margin-top: 6px; }
        .photos img { height: 130px; border: 1px solid #cbd5e1; border-radius: 4px; margin: 0 6px 6px 0; }
        .muted { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Rekap Laporan Ekspo / Free-Trial</div>
        <div class="sub muted">{{ count($items) }} laporan • dicetak {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @forelse ($items as $it)
        @php($r = $it['report'])
        <div class="card">
            <h2>{{ $r->school->name ?? 'Sekolah' }}</h2>
            <div class="meta">
                {{ optional($r->date)->format('d/m/Y') }}
                @if ($r->team_name) &bull; Tim: {{ $r->team_name }} @endif
                @if ($r->trainer) &bull; Oleh: {{ $r->trainer->name }} @endif
            </div>

            <table class="f">
                <tr>
                    <td class="k">Rating</td>
                    <td>
                        <span class="stars">
                            @for ($i = 1; $i <= 5; $i++){{ $i <= ($r->rating ?? 0) ? '★' : '☆' }}@endfor
                        </span>
                        ({{ $r->rating ?? '-' }}/5)
                    </td>
                </tr>
                <tr>
                    <td class="k">Sesuai Jadwal</td>
                    <td>
                        @php($sch = $r->on_schedule)
                        <span class="badge {{ $sch === 'ya' ? 'ok' : ($sch === 'sebagian' ? 'warn' : 'bad') }}">
                            {{ $sch ? ucfirst($sch) : '-' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="k">Antusiasme</td>
                    <td>{{ $r->enthusiasm ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Kendala</td>
                    <td>
                        @if ($r->has_issue)
                            <span class="badge bad">Ada</span> {{ $r->issue_note }}
                        @else
                            <span class="badge ok">Tidak ada</span>
                        @endif
                    </td>
                </tr>
                @if ($r->doc_url)
                    <tr><td class="k">Link Dokumentasi</td><td>{{ $r->doc_url }}</td></tr>
                @endif
            </table>

            @if ($it['photos']->isNotEmpty())
                <div class="photos">
                    @foreach ($it['photos'] as $src)
                        <img src="{{ $src }}" alt="dokumentasi">
                    @endforeach
                </div>
            @else
                <div class="muted">Tidak ada foto dokumentasi.</div>
            @endif
        </div>
    @empty
        <p class="muted">Belum ada laporan ekspo.</p>
    @endforelse
</body>
</html>
