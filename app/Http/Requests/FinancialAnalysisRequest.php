<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialAnalysisRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'period' => 'nullable|string|in:this_month,last_month,last_3_months,last_6_months,last_12_months,this_year,last_year,custom',
            'start_date' => 'nullable|date|required_if:period,custom',
            'end_date' => 'nullable|date|required_if:period,custom|after_or_equal:start_date',
            'category_id' => 'nullable|integer|exists:categories,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
            'sort' => 'nullable|string|in:date,amount,name',
            'dir' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string|max:255',
        ];
    }
}
