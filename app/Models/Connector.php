<?php

namespace App\Models;

use Database\Factories\ConnectorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ADR-0004. `secret_hash` is stored via the `encrypted` cast (reversible)
// — see the migration comment for why a true one-way hash would break
// both outbound signing and inbound verification. Never serialise
// `secret_hash` back out (06-security-threat-model.md: "never logged in
// plaintext... including in error messages"), hence $hidden below.
class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'name',
        'webhook_url',
        'secret_hash',
        'status',
        'registered_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected $casts = [
        'secret_hash' => 'encrypted',
        'registered_at' => 'datetime',
    ];

    /**
     * @return HasMany<DsarConnectorTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(DsarConnectorTask::class);
    }
}
