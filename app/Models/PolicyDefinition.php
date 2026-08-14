<?php

namespace App\Models;

use Database\Factories\PolicyDefinitionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// ADR-0001 — a single ABAC policy row, evaluated by
// App\Services\PolicyEvaluator. Deliberately no save()/delete() guard
// like AuditLogEntry/ConsentNotice: unlike those append-only records,
// policy rows are meant to be superseded via the (not-yet-built)
// policy.update sensitive action.
class PolicyDefinition extends Model
{
    /** @use HasFactory<PolicyDefinitionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'action_name',
        'version',
        'subject_conditions',
        'resource_conditions',
        'environment_conditions',
        'effect',
        'status',
    ];

    protected $casts = [
        'subject_conditions' => 'array',
        'resource_conditions' => 'array',
        'environment_conditions' => 'array',
    ];
}
