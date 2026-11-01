<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Periode laporan pemasukan (KEU-07), dipakai JSON dan export. Hak akses dicek di route. */
class IncomeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ];
    }
}
