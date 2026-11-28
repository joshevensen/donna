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
            // A message may be only files: then there is no text at all.
            'body' => ['nullable', 'required_without:file_ids', 'string', 'max:100000'],
            'file_ids' => ['sometimes', 'array', 'min:1', 'max:'.config('donna.files.max_per_message')],
            'file_ids.*' => ['integer', 'distinct'],
            'source' => ['required', Rule::enum(MessageSource::class)],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Write a message or attach a file.',
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
