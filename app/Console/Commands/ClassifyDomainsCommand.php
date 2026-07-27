<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\ClassificationService;
use Illuminate\Console\Command;

/**
 * Runs the classification rules over every registered domain, so competitors get
 * a site type (маркетплейс / инфосайт / агрегатор …). Manual classifications are
 * left alone by the service.
 */
class ClassifyDomainsCommand extends Command
{
    protected $signature = 'domains:classify {--project= : Only domains of this project}';

    protected $description = 'Classify registered domains by site type using the classification rules';

    public function handle(ClassificationService $service): int
    {
        $domains = Domain::query()
            ->with('project:id,organization_id')
            ->when($this->option('project'), fn ($q) => $q->where('project_id', (int) $this->option('project')))
            ->get();

        $classified = 0;

        foreach ($domains as $domain) {
            // project_id is a non-nullable FK, so the relation always resolves.
            if ($service->classify($domain->name, $domain->project->organization_id) !== null) {
                $classified++;
            }
        }

        $this->info("Classified {$classified} of {$domains->count()} domains.");

        return self::SUCCESS;
    }
}
