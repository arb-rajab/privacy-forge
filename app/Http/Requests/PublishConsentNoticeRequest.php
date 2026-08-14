<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishConsentNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPrivilegedFor('privacy_manager') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
