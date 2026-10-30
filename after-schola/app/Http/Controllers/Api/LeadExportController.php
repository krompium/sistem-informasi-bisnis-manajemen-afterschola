<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

// MGT-22: export Excel calon dan leads. Filter dan scope cabang sama dengan daftar (GET /leads).
class LeadExportController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADER = ['Tanggal Daftar', 'Nama Calon', 'Jenjang', 'Asal Sekolah', 'Program', 'Cabang', 'Jadwal Trial', 'Status', 'Nama Orang Tua', 'Email', 'Telepon', 'Catatan'];

    public function __invoke(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(Lead::STATUS_LABELS))],
        ]);

        $query = Lead::visibleTo($request->user())->filter($filters)
            ->with(['parent:id,name,phone', 'branch:id,name', 'program:id,name', 'trialSlot:id,starts_at']);

        return response()->streamDownload(function () use ($query) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(20, 1, 12);
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(self::HEADER, (new Style)->withFontBold(true)->withBackgroundColor('92D050')));

            // Dibaca per potong supaya export ratusan baris tidak menghabiskan memori (NFR-02).
            $query->chunkById(200, function ($leads) use ($writer) {
                foreach ($leads as $lead) {
                    $writer->addRow(Row::fromValues([
                        $this->localTime($lead->created_at),
                        $lead->name,
                        strtoupper($lead->education_level),
                        $lead->origin_school,
                        $lead->program?->name ?? '',
                        $lead->branch?->name ?? '',
                        $this->localTime($lead->trialSlot?->starts_at),
                        Lead::STATUS_LABELS[$lead->status] ?? $lead->status,
                        $lead->parent?->name ?? '',
                        $lead->parent_email,
                        $lead->parent?->phone ?? '',
                        $lead->note ?? '',
                    ]));
                }
            });

            $writer->close();
        }, 'calon-siswa-'.now()->format('Ymd').'.xlsx', ['Content-Type' => self::XLSX]);
    }

    private function localTime(?Carbon $time): string
    {
        return $time?->copy()->timezone(config('app.display_timezone'))->format('d/m/Y H:i') ?? '';
    }
}
