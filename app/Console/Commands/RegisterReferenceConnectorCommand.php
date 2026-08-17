<?php

namespace App\Console\Commands;

use App\Models\Connector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

// ADR-0004/FR-019: "a single reference/stub connector is built to prove
// the contract... no specific third-party connector ships in v1." This
// command is the registration path for that stub — a full connector
// registration admin UI is out of scope this session
// (docs/project-memory/12-session-handoff.md).
class RegisterReferenceConnectorCommand extends Command
{
    protected $signature = 'connectors:register-reference
        {--name=Reference Stub Connector : Display name for the connector}
        {--webhook-url= : Where outbound task webhooks are POSTed}';

    protected $description = 'Register the reference/stub connector used to prove the ADR-0004 webhook contract';

    public function handle(): int
    {
        $secret = Str::random(40);

        $connector = Connector::create([
            'name' => $this->option('name'),
            'webhook_url' => $this->option('webhook-url')
                ?: config('connectors.reference_connector_base_url').'/api/reference-connector/webhook',
            'secret_hash' => $secret,
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // Shown exactly once — per the threat model, this secret is never
        // logged or persisted anywhere else in plaintext (06-security-
        // threat-model.md, Secrets management).
        $this->info("Connector registered: {$connector->id}");
        $this->line("Shared secret (record this now — it will not be shown again): {$secret}");

        return self::SUCCESS;
    }
}
