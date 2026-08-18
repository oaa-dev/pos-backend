<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
