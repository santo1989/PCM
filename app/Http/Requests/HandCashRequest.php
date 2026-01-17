<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandCashRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'rules.*' => 'nullable|string|max:255',
            'types.*' => 'nullable|string|max:255',
            'name.*' => 'nullable|string|max:255',
            'date.*' => 'nullable|date',
            'amount.*' => 'nullable|numeric',
        ];
    }

    /**
     * Normalize input before validation: uppercase types and rules.
     */
    protected function prepareForValidation()
    {
        $data = $this->all();

        if ($this->has('types')) {
            $types = $this->input('types');
            if (is_array($types)) {
                $data['types'] = array_map(function ($v) {
                    return $v === null ? null : strtoupper($v);
                }, $types);
            } else {
                $data['types'] = $types === null ? null : strtoupper($types);
            }
        }

        if ($this->has('rules')) {
            $rules = $this->input('rules');
            if (is_array($rules)) {
                $data['rules'] = array_map(function ($v) {
                    return $v === null ? null : strtoupper($v);
                }, $rules);
            } else {
                $data['rules'] = $rules === null ? null : strtoupper($rules);
            }
        }

        $this->replace($data);
    }
}
