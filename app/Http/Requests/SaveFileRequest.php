<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Josh's own edit of a file, with the version it was made on top of. The
 * text arrives exactly as typed: this route is left out of the middleware
 * that trims strings and turns empty ones into null (bootstrap/app.php).
 */
class SaveFileRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['present', 'string', 'max:'.(config('donna.files.max_kilobytes') * 1024)],
            'base_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
