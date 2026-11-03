<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filter laporan pemasukan (KEU-07); dipakai JSON, export Excel, dan export PDF. Permission dicek di route. */
class IncomeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
        ];
    }
}
