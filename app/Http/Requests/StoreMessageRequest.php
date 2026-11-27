<?php

namespace App\Http\Requests;

use App\Enums\MessageSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:100000'],
            'source' => ['required', Rule::enum(MessageSource::class)],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * The key travels as the `Idempotency-Key` header; validate it with the rest.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
