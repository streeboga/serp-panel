<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Support\MutedCodes;
use SerpAudit\CheckRegistry;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final readonly class PageAuditor
{
    public function __construct(
        private CheckRegistry $registry,
    ) {}

    /**
     * Прогоняет выбранные проверки по одному разобранному документу.
     *
     * @param  array<int, string>|null  $categories  пусто — все категории
     * @param  array<int, string>|null  $codes  пусто — все проверки категорий
     * @param  array<string, string>  $muted  код находки → причина заглушения
     * @return array{
     *     score: int,
     *     findings: array<int, array<string, mixed>>,
     *     metrics: array<string, mixed>,
     *     issues_critical: int,
     *     issues_warning: int,
     *     issues_notice: int,
     *     issues_muted: int
     * }
     */
    public function audit(PageContext $context, ?array $categories = null, ?array $codes = null, array $muted = []): array
    {
        $findings = [];
        $metrics = [];

        foreach ($this->registry->select($categories, $codes) as $check) {
            $findings = [...$findings, ...$check->run($context)];
            $metrics = [...$metrics, ...$check->metrics($context)];
        }

        return [...self::summarize($findings, $muted), 'metrics' => $metrics];
    }

    /**
     * Оценка и счётчики по списку находок. Используется и для страницы, и для сайта.
     *
     * Заглушённая находка остаётся в списке с пометкой `muted`, но не попадает
     * ни в счётчики, ни в штраф оценки: иначе прогон показывает 1589 находок
     * там, где разбирать надо 26, и число перестают читать вовсе.
     *
     * @param  array<int, Finding>  $findings
     * @param  array<string, string>  $muted  код находки → причина заглушения
     * @return array{
     *     score: int,
     *     findings: array<int, array<string, mixed>>,
     *     issues_critical: int,
     *     issues_warning: int,
     *     issues_notice: int,
     *     issues_muted: int
     * }
     */
    public static function summarize(array $findings, array $muted = []): array
    {
        $counts = [Severity::Critical->value => 0, Severity::Warning->value => 0, Severity::Notice->value => 0];
        $penalty = 0;

        $marked = MutedCodes::apply(
            array_map(static fn (Finding $f): array => $f->toArray(), $findings),
            MutedCodes::normalize($muted),
        );

        foreach ($marked['findings'] as $finding) {
            if (MutedCodes::isMuted($finding)) {
                continue;
            }

            $severity = Severity::from((string) $finding['severity']);
            $counts[$severity->value]++;
            $penalty += $severity->penalty();
        }

        return [
            'score' => max(0, 100 - $penalty),
            'findings' => $marked['findings'],
            'issues_critical' => $counts[Severity::Critical->value],
            'issues_warning' => $counts[Severity::Warning->value],
            'issues_notice' => $counts[Severity::Notice->value],
            'issues_muted' => $marked['muted'],
        ];
    }
}
