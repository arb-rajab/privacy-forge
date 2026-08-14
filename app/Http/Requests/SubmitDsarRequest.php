<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDsarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_type' => ['required', 'string', 'in:access,export,erasure'],
            'subject_identifier' => ['required', 'string', 'max:512'],
        ];
    }
}
