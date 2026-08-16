<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsentPurposeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lawful_basis' => ['required', 'string', 'in:consent,contract,legal_obligation,vital_interests,public_task,legitimate_interests'],
            'data_category_id' => ['nullable', 'uuid', 'exists:data_categories,id'],
            'data_subjects_description' => ['nullable', 'string'],
        ];
    }
}
