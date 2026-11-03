<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Notifications\Notification;

/** SYS-03: raport dikembalikan koordinator -> trainer penulis (hanya in-app). */
class ReportReturnedNotification extends Notification
{
    private string $student;

    private string $period;

    private int $evaluationId;

    public function __construct(Evaluation $evaluation, private string $note)
    {
        $this->student = $evaluation->enrollment->student->name;
        $this->period = $evaluation->period;
        $this->evaluationId = $evaluation->id;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_returned',
            'message' => "Raport {$this->student} periode {$this->period} dikembalikan: {$this->note}",
            'evaluation_id' => $this->evaluationId,
        ];
    }
}
