<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Authorisation for this request is the policy.update ABAC gate in
// App\Http\Controllers\Admin\PolicyController, not this class — unlike
// StoreConsentPurposeRequest, policy.update is one of ADR-0001's named
// sensitive actions, so it goes through PolicyEvaluator rather than a
// FormRequest::authorize() role check. The route's ['web','auth']
// middleware already guarantees a session exists before this class runs.
class UpdatePolicyDefinitionRequest extends FormRequest
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
            'subject_conditions' => ['nullable', 'array'],
            'resource_conditions' => ['nullable', 'array'],
            'environment_conditions' => ['nullable', 'array'],
            'effect' => ['required', 'string', 'in:allow,deny'],
        ];
    }
}
