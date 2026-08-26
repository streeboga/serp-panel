<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ClassificationRuleRepositoryInterface;
use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\Contracts\Repositories\ConnectedAccountRepositoryInterface;
use App\Contracts\Repositories\DomainClassificationRepositoryInterface;
use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\OrganizationRepositoryInterface;
use App\Contracts\Repositories\PageableRepositoryInterface;
use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Contracts\Repositories\PageSerpMatchRepositoryInterface;
use App\Contracts\Repositories\PositionAlertRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\RegionRepositoryInterface;
use App\Contracts\Repositories\ScrapeJobRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Contracts\Repositories\ScrapeScheduleRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Contracts\Repositories\SiteTypeRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\WordstatFrequencyRepositoryInterface;
use App\Contracts\Repositories\WordstatScheduleRepositoryInterface;
use App\Contracts\Repositories\WordstatSuggestionRepositoryInterface;
use App\Contracts\Repositories\WordstatTrendRepositoryInterface;
use App\Repositories\Eloquent\AuditResourceRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\ClassificationRuleRepository;
use App\Repositories\Eloquent\ClusterRepository;
use App\Repositories\Eloquent\ConnectedAccountRepository;
use App\Repositories\Eloquent\DomainClassificationRepository;
use App\Repositories\Eloquent\DomainIndexResultRepository;
use App\Repositories\Eloquent\DomainRepository;
use App\Repositories\Eloquent\KeywordRepository;
use App\Repositories\Eloquent\OrganizationRepository;
use App\Repositories\Eloquent\PageableRepository;
use App\Repositories\Eloquent\PageAuditResultRepository;
use App\Repositories\Eloquent\PageRepository;
use App\Repositories\Eloquent\PageSerpMatchRepository;
use App\Repositories\Eloquent\PositionAlertRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\RegionRepository;
use App\Repositories\Eloquent\ScrapeJobRepository;
use App\Repositories\Eloquent\ScraperRepository;
use App\Repositories\Eloquent\ScrapeScheduleRepository;
use App\Repositories\Eloquent\SerpResultRepository;
use App\Repositories\Eloquent\SerpSnapshotRepository;
use App\Repositories\Eloquent\SiteAuditRepository;
use App\Repositories\Eloquent\SiteTypeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WordstatFrequencyRepository;
use App\Repositories\Eloquent\WordstatScheduleRepository;
use App\Repositories\Eloquent\WordstatSuggestionRepository;
use App\Repositories\Eloquent\WordstatTrendRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProjectRepositoryInterface::class => ProjectRepository::class,
        DomainRepositoryInterface::class => DomainRepository::class,
        KeywordRepositoryInterface::class => KeywordRepository::class,
        SerpSnapshotRepositoryInterface::class => SerpSnapshotRepository::class,
        ScraperRepositoryInterface::class => ScraperRepository::class,
        ScrapeScheduleRepositoryInterface::class => ScrapeScheduleRepository::class,
        ClassificationRuleRepositoryInterface::class => ClassificationRuleRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ClusterRepositoryInterface::class => ClusterRepository::class,
        OrganizationRepositoryInterface::class => OrganizationRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        PositionAlertRepositoryInterface::class => PositionAlertRepository::class,
        WordstatFrequencyRepositoryInterface::class => WordstatFrequencyRepository::class,
        WordstatTrendRepositoryInterface::class => WordstatTrendRepository::class,
        WordstatSuggestionRepositoryInterface::class => WordstatSuggestionRepository::class,
        WordstatScheduleRepositoryInterface::class => WordstatScheduleRepository::class,
        SerpResultRepositoryInterface::class => SerpResultRepository::class,
        ScrapeJobRepositoryInterface::class => ScrapeJobRepository::class,
        RegionRepositoryInterface::class => RegionRepository::class,
        SiteTypeRepositoryInterface::class => SiteTypeRepository::class,
        DomainClassificationRepositoryInterface::class => DomainClassificationRepository::class,
        ConnectedAccountRepositoryInterface::class => ConnectedAccountRepository::class,
        PageRepositoryInterface::class => PageRepository::class,
        PageableRepositoryInterface::class => PageableRepository::class,
        PageSerpMatchRepositoryInterface::class => PageSerpMatchRepository::class,
        DomainIndexResultRepositoryInterface::class => DomainIndexResultRepository::class,
        SiteAuditRepositoryInterface::class => SiteAuditRepository::class,
        PageAuditResultRepositoryInterface::class => PageAuditResultRepository::class,
        AuditResourceRepositoryInterface::class => AuditResourceRepository::class,
    ];
}
