<?php

namespace App\Http\Requests;

use App\Enums\EvalRating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', Rule::enum(EvalRating::class)],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
