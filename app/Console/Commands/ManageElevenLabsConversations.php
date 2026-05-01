<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ManageElevenLabsConversations extends Command
{
    protected $signature = 'elevenlabs:conversations
                            {action=list : Action to perform (list, delete, purge)}
                            {--id= : Conversation ID to delete (use with delete action)}
                            {--limit=50 : Max conversations to fetch per page (1–100)}
                            {--all : Fetch all pages when listing}
                            {--force : Skip confirmation prompts}';

    protected $description = 'List and delete ElevenLabs agent conversations';

    private string $baseUrl;
    private string $apiKey;
    private string $agentId;

    public function handle(): int
    {
        $this->baseUrl = config('services.elevenlabs.base_url');
        $this->apiKey  = config('services.elevenlabs.api_key');
        $this->agentId = config('services.elevenlabs.agent_id');

        if (! $this->apiKey || ! $this->agentId) {
            $this->error('ELEVENLABS_API_KEY and ELEVENLABS_AGENT_ID must be set in .env');
            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'list'   => $this->listConversations(),
            'delete' => $this->deleteSingle(),
            'purge'  => $this->purgeAll(),
            default  => $this->invalidAction(),
        };
    }

    private function listConversations(): int
    {
        $limit    = (int) $this->option('limit');
        $fetchAll = $this->option('all');
        $cursor   = null;
        $rows     = [];
        $page     = 0;

        $this->info("Fetching conversations for agent {$this->agentId}...");

        do {
            $params = array_filter([
                'agent_id'  => $this->agentId,
                'page_size' => min($limit, 100),
                'cursor'    => $cursor,
            ]);

            $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
                ->get("{$this->baseUrl}/convai/conversations", $params);

            if (! $response->successful()) {
                $this->error("API error {$response->status()}: {$response->body()}");
                return self::FAILURE;
            }

            $data          = $response->json();
            $conversations = $data['conversations'] ?? [];
            $cursor        = $data['next_cursor'] ?? null;
            $page++;

            foreach ($conversations as $c) {
                $rows[] = [
                    $c['conversation_id'] ?? '—',
                    isset($c['start_time_unix_secs'])
                        ? date('Y-m-d H:i:s', $c['start_time_unix_secs'])
                        : '—',
                    $c['status'] ?? '—',
                    isset($c['call_duration_secs'])
                        ? gmdate('i:s', (int) $c['call_duration_secs'])
                        : '—',
                    $c['message_count'] ?? '—',
                ];
            }

            if (! $fetchAll && count($rows) >= $limit) {
                break;
            }
        } while ($fetchAll && $cursor);

        if (empty($rows)) {
            $this->info('No conversations found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Conversation ID', 'Started At', 'Status', 'Duration', 'Messages'],
            $rows
        );

        $this->info(sprintf('Showing %d conversation(s).%s',
            count($rows),
            $cursor ? ' More exist — run with --all to fetch everything.' : ''
        ));

        return self::SUCCESS;
    }

    private function deleteSingle(): int
    {
        $id = $this->option('id');

        if (! $id) {
            $this->error('Provide a conversation ID with --id=<conversation_id>');
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete conversation {$id}?")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        return $this->deleteConversation($id) ? self::SUCCESS : self::FAILURE;
    }

    private function purgeAll(): int
    {
        if (! $this->option('force') &&
            ! $this->confirm('This will permanently delete ALL conversations for this agent. Are you sure?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->info("Fetching all conversation IDs for agent {$this->agentId}...");

        $ids = $this->fetchAllIds();

        if ($ids === null) {
            return self::FAILURE;
        }

        if (empty($ids)) {
            $this->info('No conversations found.');
            return self::SUCCESS;
        }

        $this->info('Deleting '.count($ids).' conversation(s)...');

        $deleted = 0;
        $failed  = 0;
        $bar     = $this->output->createProgressBar(count($ids));
        $bar->start();

        foreach ($ids as $id) {
            if ($this->deleteConversation($id, silent: true)) {
                $deleted++;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Deleted: {$deleted}, Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fetchAllIds(): ?array
    {
        $ids    = [];
        $cursor = null;

        do {
            $params = array_filter([
                'agent_id'  => $this->agentId,
                'page_size' => 100,
                'cursor'    => $cursor,
            ]);

            $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
                ->get("{$this->baseUrl}/convai/conversations", $params);

            if (! $response->successful()) {
                $this->error("API error {$response->status()}: {$response->body()}");
                return null;
            }

            $data   = $response->json();
            $cursor = $data['next_cursor'] ?? null;

            foreach ($data['conversations'] ?? [] as $c) {
                if ($id = $c['conversation_id'] ?? null) {
                    $ids[] = $id;
                }
            }
        } while ($cursor);

        return $ids;
    }

    private function deleteConversation(string $id, bool $silent = false): bool
    {
        $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
            ->delete("{$this->baseUrl}/convai/conversations/{$id}");

        if ($response->successful()) {
            if (! $silent) {
                $this->info("Deleted conversation {$id}");
            }
            return true;
        }

        if (! $silent) {
            $this->error("Failed to delete {$id}: {$response->status()} {$response->body()}");
        }

        return false;
    }

    private function invalidAction(): int
    {
        $this->error("Unknown action '{$this->argument('action')}'. Valid actions: list, delete, purge");
        return self::FAILURE;
    }
}
