<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Authorisation is the retention.policy.manage ABAC gate in
// App\Http\Controllers\Admin\RetentionPolicyController, not this class —
// see StoreDataCategoryRequest for the same reasoning.
class StoreRetentionPolicyRequest extends FormRequest
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
            'data_category_id' => ['required', 'uuid', 'exists:data_categories,id'],
            'retention_period_days' => ['required', 'integer', 'min:1'],
            'post_expiry_action' => ['required', 'string', 'in:erase,anonymise'],
        ];
    }
}
