<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\OrganizationRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Models\Organization;

final readonly class BillingService
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private ProjectRepositoryInterface $projectRepository,
        private ScraperRepositoryInterface $scraperRepository,
        private KeywordRepositoryInterface $keywordRepository,
    ) {}

    public const array TIERS = [
        'free' => ['max_keywords' => 100, 'max_projects' => 3, 'max_scrapers' => 1],
        'starter' => ['max_keywords' => 1000, 'max_projects' => 10, 'max_scrapers' => 3],
        'pro' => ['max_keywords' => 10000, 'max_projects' => 50, 'max_scrapers' => 10],
        'enterprise' => ['max_keywords' => 100000, 'max_projects' => 500, 'max_scrapers' => 100],
    ];

    /** @return array<string, mixed> */
    public function usage(Organization $organization): array
    {
        $projectCount = $this->projectRepository->countByOrganization($organization->id);
        $scraperCount = $this->scraperRepository->countByOrganization($organization->id);
        $keywordCount = $this->keywordRepository->countByOrganization($organization->id);

        return [
            'tier' => $organization->billing_tier ?? 'free',
            'limits' => self::TIERS[$organization->billing_tier ?? 'free'],
            'usage' => [
                'keywords' => $keywordCount,
                'projects' => $projectCount,
                'scrapers' => $scraperCount,
            ],
        ];
    }

    public function checkLimit(Organization $organization, string $resource): bool
    {
        $tier = $organization->billing_tier ?? 'free';
        $limits = self::TIERS[$tier] ?? self::TIERS['free'];

        return match ($resource) {
            'keywords' => $this->keywordRepository->countByOrganization($organization->id) < $limits['max_keywords'],
            'projects' => $this->projectRepository->countByOrganization($organization->id) < $limits['max_projects'],
            'scrapers' => $this->scraperRepository->countByOrganization($organization->id) < $limits['max_scrapers'],
            default => true,
        };
    }

    public function updateTier(Organization $organization, string $tier): Organization
    {
        $tierConfig = self::TIERS[$tier] ?? null;
        if (! $tierConfig) {
            throw new \InvalidArgumentException(__('billing.invalid_tier'));
        }

        return $this->organizationRepository->update($organization, [
            'billing_tier' => $tier,
            'max_keywords' => $tierConfig['max_keywords'],
            'max_projects' => $tierConfig['max_projects'],
            'max_scrapers' => $tierConfig['max_scrapers'],
        ]);
    }
}
