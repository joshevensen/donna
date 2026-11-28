<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The keep switch and a rename. A file's text is saved through its own endpoint.
 */
class UpdateFileRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'kept' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'min:1', 'max:200', 'not_regex:#[/\\\\\x00-\x1F]#'],
        ];
    }
}
