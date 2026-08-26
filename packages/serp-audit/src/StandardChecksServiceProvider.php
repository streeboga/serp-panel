<?php

declare(strict_types=1);

namespace SerpAudit;

use Illuminate\Support\ServiceProvider;
use SerpAudit\Checks\A11y\AccessibleNameCheck;
use SerpAudit\Checks\A11y\DuplicateIdCheck;
use SerpAudit\Checks\A11y\FormLabelCheck;
use SerpAudit\Checks\A11y\LandmarkCheck;
use SerpAudit\Checks\A11y\SkipLinkCheck;
use SerpAudit\Checks\A11y\TableHeaderCheck;
use SerpAudit\Checks\Content\NauseaCheck;
use SerpAudit\Checks\Content\RelevanceCheck;
use SerpAudit\Checks\Content\TextCheck;
use SerpAudit\Checks\Content\WaterCheck;
use SerpAudit\Checks\Http\AnalyticsCheck;
use SerpAudit\Checks\Http\AssetsCheck;
use SerpAudit\Checks\Http\PayloadCheck;
use SerpAudit\Checks\Http\RedirectCheck;
use SerpAudit\Checks\Http\SecurityHeadersCheck;
use SerpAudit\Checks\Http\StatusCheck;
use SerpAudit\Checks\Http\TechnologyCheck;
use SerpAudit\Checks\Images\AltCheck;
use SerpAudit\Checks\Images\SourceCheck;
use SerpAudit\Checks\Legal\ConsentCheck;
use SerpAudit\Checks\Legal\PolicyLinkCheck;
use SerpAudit\Checks\Links\AnchorCheck;
use SerpAudit\Checks\Links\ExternalLinkCheck;
use SerpAudit\Checks\Meta\DescriptionCheck;
use SerpAudit\Checks\Meta\DocumentCheck;
use SerpAudit\Checks\Meta\HeadingsCheck;
use SerpAudit\Checks\Meta\IndexingCheck;
use SerpAudit\Checks\Meta\LanguageCheck;
use SerpAudit\Checks\Meta\LegacyCheck;
use SerpAudit\Checks\Meta\SocialCheck;
use SerpAudit\Checks\Meta\TitleCheck;
use SerpAudit\Text\TextAnalyzer;

/**
 * Стандартный набор проверок. Свой пакет устроен так же: получает реестр
 * и кладёт в него свои проверки — больше приложению ничего знать не нужно.
 */
final class StandardChecksServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string<Contracts\PageCheck>> Проверки без своих зависимостей. */
    private const SIMPLE = [
        StatusCheck::class,
        RedirectCheck::class,
        PayloadCheck::class,
        SecurityHeadersCheck::class,
        AnalyticsCheck::class,
        TechnologyCheck::class,
        AssetsCheck::class,
        LandmarkCheck::class,
        SkipLinkCheck::class,
        DuplicateIdCheck::class,
        FormLabelCheck::class,
        TableHeaderCheck::class,
        AccessibleNameCheck::class,
        ConsentCheck::class,
        PolicyLinkCheck::class,
        TitleCheck::class,
        DescriptionCheck::class,
        HeadingsCheck::class,
        IndexingCheck::class,
        DocumentCheck::class,
        SocialCheck::class,
        LegacyCheck::class,
        LanguageCheck::class,
        ExternalLinkCheck::class,
        AnchorCheck::class,
        AltCheck::class,
        SourceCheck::class,
    ];

    /** @var array<int, class-string<Contracts\PageCheck>> Проверки, которым нужен разбор текста. */
    private const TEXTUAL = [
        TextCheck::class,
        WaterCheck::class,
        NauseaCheck::class,
        RelevanceCheck::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CheckRegistry::class);
        $this->app->singleton(TextAnalyzer::class);

        // Пороги приложение задаёт конфигом; без него берутся дефолты пакета.
        $this->app->singleton(Thresholds::class, static fn (): Thresholds => new Thresholds(
            (array) config('audit.thresholds', []),
        ));
    }

    public function boot(CheckRegistry $registry, Thresholds $thresholds, TextAnalyzer $analyzer): void
    {
        foreach (self::SIMPLE as $class) {
            $registry->register(new $class($thresholds));
        }

        foreach (self::TEXTUAL as $class) {
            $registry->register(new $class($analyzer, $thresholds));
        }
    }
}
