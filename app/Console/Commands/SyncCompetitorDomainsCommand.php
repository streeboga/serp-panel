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

                $newPages += $this->syncPages($service, $project->id, $project->organization_id, $domain);
            }
        }

        $this->info("Registered {$newDomains} competitor domains and {$newPages} pages.");

        return self::SUCCESS;
    }

    /** Store each URL of this domain that ranks for one of our phrases. */
    private function syncPages(CompetitorService $service, int $projectId, int $organizationId, Domain $domain): int
    {
        $rows = collect($service->getCompetitorPages($projectId, $organizationId, $domain->name))
            ->unique('url')
            ->map(fn (array $p): array => [
                'project_id' => $projectId,
                'domain_id' => $domain->id,
                'url' => $p['url'],
                // Page::boot() normalises this on save; bulk upsert bypasses the model.
                'path' => parse_url((string) $p['url'], PHP_URL_PATH) ?: '/',
                'title' => $p['title'],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('pages')->upsert($chunk, ['project_id', 'url'], ['domain_id', 'path', 'title', 'updated_at']);
        }

        return count($rows);
    }
}
