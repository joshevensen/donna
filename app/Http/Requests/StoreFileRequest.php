<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One uploaded file. Only its size is checked here; what it is gets decided
 * from its content, never from its name or the type the browser claims.
 */
class StoreFileRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('donna.files.max_kilobytes')],
        ];
    }

    /**
     * The reason shown on the chip. An upload PHP itself turned away was too big for the server.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to attach.',
            'file.file' => 'Too large. Max 10 MB',
            'file.uploaded' => 'Too large. Max 10 MB',
            'file.max' => 'Too large. Max 10 MB',
        ];
    }
}
