<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Legal;

use DOMElement;
use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Ссылка на политику обработки персональных данных — критерий K3 приёмки eq.team.
 * По 152-ФЗ политика должна быть доступна с любой страницы, обычно из подвала.
 */
final class PolicyLinkCheck extends Check
{
    /** @var array<int, string> */
    private const HREF_MARKERS = ['polit', 'privacy', 'personal-data', 'pdn', 'confidential'];

    /** @var array<int, string> */
    private const TEXT_MARKERS = ['политик', 'конфиденциальн', 'персональных данных', 'privacy'];

    public function code(): string
    {
        return 'legal.policy_link';
    }

    public function category(): string
    {
        return Category::LEGAL;
    }

    public function title(): string
    {
        return 'Ссылка на политику обработки данных';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        if ($this->policyUrl($context) !== null) {
            return [];
        }

        return [$this->finding('missing', Severity::Warning,
            'На странице нет ссылки на политику обработки персональных данных',
            null, 'ссылка в подвале')];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['policy_url' => $this->policyUrl($context)];
    }

    private function policyUrl(PageContext $context): ?string
    {
        foreach ($context->query('//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = mb_strtolower($node->getAttribute('href'));
            $text = mb_strtolower(trim($node->textContent));

            foreach (self::HREF_MARKERS as $marker) {
                if (str_contains($href, $marker)) {
                    return $context->absolute($node->getAttribute('href'));
                }
            }

            foreach (self::TEXT_MARKERS as $marker) {
                if (str_contains($text, $marker)) {
                    return $context->absolute($node->getAttribute('href'));
                }
            }
        }

        return null;
    }
}
