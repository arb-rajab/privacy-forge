<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptureConsentRequest extends FormRequest
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
            'purpose_id' => ['required', 'uuid', 'exists:consent_purposes,id'],
            'notice_version' => ['required', 'integer', 'min:1'],
            'subject_identifier' => ['required', 'string', 'max:512'],
        ];
    }
}
