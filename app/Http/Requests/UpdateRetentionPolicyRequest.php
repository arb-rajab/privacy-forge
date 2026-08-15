<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Authorisation is the retention.policy.manage ABAC gate in
// App\Http\Controllers\Admin\RetentionPolicyController, not this class —
// see StoreDataCategoryRequest for the same reasoning. data_category_id
// is deliberately not accepted here: it is the versioning grouping key
// (mirroring PolicyDefinition's immutable action_name), so a policy's
// category cannot change across versions — defining a policy for a
// different category is a new policy, not an update to this one.
class UpdateRetentionPolicyRequest extends FormRequest
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
            'retention_period_days' => ['required', 'integer', 'min:1'],
            'post_expiry_action' => ['required', 'string', 'in:erase,anonymise'],
        ];
    }
}
