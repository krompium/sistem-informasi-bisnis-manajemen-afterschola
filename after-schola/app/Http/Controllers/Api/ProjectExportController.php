<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ProjectExportController extends Controller
{
    private const PROJECT_STATUS = ['planning' => 'Perencanaan', 'ongoing' => 'Berjalan', 'done' => 'Selesai'];

    private const HEADER = ['Judul', 'Assignee', 'Kategori', 'Prioritas', 'Status', 'Progress (%)', 'Tenggat', 'Checklist', 'Tautan GitHub'];

    public function __invoke(Request $request, Project $project)
    {
        // Otorisasi dulu (non-anggota 404), baru validasi.
        $this->authorize('view', $project);

        $format = $request->validate(['format' => ['required', 'in:xlsx,pdf']])['format'];

        $report = $this->buildReport($project);
        $filename = 'laporan-proyek-'.(Str::slug($project->name) ?: $project->id);

        if ($format === 'pdf') {
            return Pdf::loadView('exports.project', ['report' => $report, 'header' => self::HEADER])
                ->setPaper('a4', 'landscape')
                ->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($report) {
            $writer = new Writer;
            $options = $writer->getOptions();
            $options->setColumnWidth(36, 1);
            $options->setColumnWidth(22, 2);
            $options->setColumnWidthForRange(14, 3, 8);
            $options->setColumnWidth(44, 9);
            $writer->openToFile('php://output');

            $bold = (new Style)->withFontBold(true);
            $head = (new Style)->withFontBold(true)->withBackgroundColor('92D050');

            $writer->addRow(Row::fromValuesWithStyle(['LAPORAN PROGRES PROYEK'], (new Style)->withFontBold(true)->withFontSize(14)));
            $writer->addRow(Row::fromValuesWithStyle([$report['name']], $bold));
            $writer->addRow(Row::fromValues(['Team: '.$report['team'].' | Status: '.$report['status'].' | '.$report['period']]));
            $writer->addRow(Row::fromValues([
                'Total task: '.$report['summary']['total'].' | Rata-rata progres: '.$report['summary']['avg_progress'].'% | Terlambat: '.$report['summary']['overdue'],
            ]));
            $writer->addRow(Row::fromValues([collect($report['summary']['by_status'])
                ->map(fn ($count, $label) => "{$label}: {$count}")->implode(' | ')]));
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValuesWithStyle(self::HEADER, $head));

            foreach ($report['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Data yang sama untuk Excel dan PDF. */
    private function buildReport(Project $project): array
    {
        $project->load('team');
        $tasks = $project->tasks()
            ->with('assignee')
            ->withCount(['checklistItems', 'checklistItems as checklist_done_count' => fn ($q) => $q->where('is_done', true)])
            ->orderBy('id')
            ->get();

        return [
            'name' => $project->name,
            'team' => $project->team->name,
            'status' => self::PROJECT_STATUS[$project->status->value] ?? $project->status->value,
            'period' => $project->start_date->format('d/m/Y').' – '.$project->due_date->format('d/m/Y'),
            'summary' => [
                'total' => $tasks->count(),
                'avg_progress' => $tasks->isEmpty() ? 0 : (int) round($tasks->avg('progress')),
                'overdue' => $tasks->filter(fn ($t) => $t->status !== TaskStatus::Done && $t->due_date->lt(today()))->count(),
                'by_status' => collect(TaskStatus::cases())
                    ->mapWithKeys(fn ($s) => [$s->label() => $tasks->filter(fn ($t) => $t->status === $s)->count()])
                    ->all(),
            ],
            'rows' => $tasks->map(fn ($t) => [
                $t->title,
                $t->assignee?->name ?? '-',
                $t->category->label(),
                $t->priority->label(),
                $t->status->label(),
                $t->progress,
                $t->due_date->format('d/m/Y'),
                $t->checklist_items_count ? "{$t->checklist_done_count}/{$t->checklist_items_count}" : '-',
                $t->github_url ?? '',
            ])->all(),
        ];
    }
}
