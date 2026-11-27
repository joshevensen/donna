<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexMessagesRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'before' => ['sometimes', 'integer', 'min:1'],
            'after' => ['sometimes', 'integer', 'min:0', 'prohibits:before'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
