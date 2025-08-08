<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgentKey;
use Illuminate\Support\Str;

class GenerateAgentKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:generate-keys {count=5 : Number of agent keys to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sample agent keys for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("Generating {$count} agent keys...");

        for ($i = 1; $i <= $count; $i++) {
            $agentKey = AgentKey::create([
                'agent_key' => 'AK_' . Str::random(16),
                'name' => "Agent_{$i}",
                'agent_host' => "host_{$i}.example.com",
                'status' => true,
                'expires_at' => now()->addDays(30), // Valid for 30 days
            ]);

            $this->line("Generated agent key: {$agentKey->agent_key} for {$agentKey->name}");
        }

        $this->info("Successfully generated {$count} agent keys!");

        // Display all active agent keys
        $this->info("\nActive agent keys:");
        $activeKeys = AgentKey::where('status', true)->get();

        $headers = ['ID', 'Agent Key', 'Name', 'Host', 'Status', 'Expires At'];
        $rows = $activeKeys->map(function ($key) {
            return [
                $key->id,
                $key->agent_key,
                $key->name,
                $key->agent_host,
                $key->status ? 'Active' : 'Inactive',
                $key->expires_at ? $key->expires_at->format('Y-m-d H:i:s') : 'Never'
            ];
        })->toArray();

        $this->table($headers, $rows);
    }
}
