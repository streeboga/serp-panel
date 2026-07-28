<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\Project;
use App\Services\CompetitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Registers the competitor sites found in our SERPs as project domains, together
 * with the pages of theirs that rank for our phrases — so they show up in the
 * project's Domains section and can be drilled into like any tracked domain.
 */
class SyncCompetitorDomainsCommand extends Command
{
    protected $signature = 'competitors:sync
        {--project= : Only this project id}
        {--min-keywords=3 : Register a domain once it ranks for at least this many phrases}';

    protected $description = 'Add competitor domains and their ranking pages to the project';

    public function handle(CompetitorService $service): int
    {
        $minKeywords = max(1, (int) $this->option('min-keywords'));

        $projects = Project::query()
            ->when($this->option('project'), fn ($q) => $q->whereKey((int) $this->option('project')))
            ->get();

        $newDomains = 0;
        $newPages = 0;

        foreach ($projects as $project) {
            $competitors = collect($service->getCompetitors($project->id, $project->organization_id))
                ->filter(fn (array $c): bool => ! $c['is_own'] && (int) $c['keyword_count'] >= $minKeywords);

            foreach ($competitors as $competitor) {
                $domain = Domain::firstOrCreate(
                    ['project_id' => $project->id, 'name' => $competitor['domain']],
                    ['is_own' => false, 'type' => DomainType::Competitor],
                );

                if ($domain->wasRecentlyCreated) {
                    $newDomains++;
                }

                $newPages += $this->syncPages($service, $project->id, $domain);
            }
        }

        $this->info("Registered {$newDomains} competitor domains and {$newPages} pages.");

        return self::SUCCESS;
    }

    /**
     * Store each URL of this domain that ranks for one of our phrases. Rows are
     * streamed and written in batches — a project-wide sync used to materialise
     * every competitor URL at once, which is what exhausted memory on the box.
     */
    private function syncPages(CompetitorService $service, int $projectId, Domain $domain): int
    {
        $written = 0;
        $batch = [];
        $seen = [];

        foreach ($service->lazyCompetitorPages($projectId, $domain->name) as $row) {
            // Postgres rejects a batch that touches the same conflict key twice.
            if (isset($seen[$row->url])) {
                continue;
            }
            $seen[$row->url] = true;

            $batch[] = [
                'project_id' => $projectId,
                'domain_id' => $domain->id,
                'url' => $row->url,
                // Page::boot() normalises this on save; bulk upsert bypasses the model.
                'path' => parse_url((string) $row->url, PHP_URL_PATH) ?: '/',
                'title' => $row->title,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 200) {
                $this->flush($batch);
                $written += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flush($batch);
            $written += count($batch);
        }

        return $written;
    }

    /** @param array<int, array<string, mixed>> $batch */
    private function flush(array $batch): void
    {
        DB::table('pages')->upsert($batch, ['project_id', 'url'], ['domain_id', 'path', 'title', 'updated_at']);
    }
}
