<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Authorisation is the retention.policy.manage ABAC gate in
// App\Http\Controllers\Admin\DataCategoryController, not this class —
// same reasoning as UpdatePolicyDefinitionRequest: this is one of
// ADR-0001's registered sensitive actions, so it goes through
// PolicyEvaluator rather than a FormRequest::authorize() role check.
class StoreDataCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sensitivity' => ['required', 'string', 'in:standard,elevated,special_category'],
            'subject_table' => ['required', 'string', 'in:consent_records,dsar_requests'],
        ];
    }
}
