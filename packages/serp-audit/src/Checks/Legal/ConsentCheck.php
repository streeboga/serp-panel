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
 * Согласие на обработку персональных данных у форм — критерий K2 приёмки eq.team.
 * Для 152-ФЗ форма, собирающая контакты без отмечаемого согласия, это нарушение.
 */
final class ConsentCheck extends Check
{
    /** Поля, из-за которых форма считается собирающей персональные данные. */
    private const PERSONAL = ['email', 'tel', 'phone', 'name', 'fio', 'imya'];

    /** @var array<int, string> */
    private const CONSENT_WORDS = ['согласи', 'обработку персональных', 'персональных данных', 'политик'];

    public function code(): string
    {
        return 'legal.consent';
    }

    public function category(): string
    {
        return Category::LEGAL;
    }

    public function title(): string
    {
        return 'Согласие на обработку персональных данных у форм';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $without = 0;

        foreach ($context->query('//form') as $form) {
            if (! $form instanceof DOMElement || ! $this->collectsPersonalData($form)) {
                continue;
            }

            if (! $this->hasConsent($form)) {
                $without++;
            }
        }

        if ($without === 0) {
            return [];
        }

        return [$this->finding('missing', Severity::Critical,
            'Формы собирают персональные данные без согласия на их обработку', $without, 0)];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['forms' => $context->count('//form')];
    }

    private function collectsPersonalData(DOMElement $form): bool
    {
        foreach ($form->getElementsByTagName('input') as $input) {
            $haystack = mb_strtolower(
                $input->getAttribute('type').' '.$input->getAttribute('name')
                .' '.$input->getAttribute('id').' '.$input->getAttribute('placeholder')
            );

            foreach (self::PERSONAL as $marker) {
                if (str_contains($haystack, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasConsent(DOMElement $form): bool
    {
        $checkboxes = 0;

        foreach ($form->getElementsByTagName('input') as $input) {
            if (mb_strtolower($input->getAttribute('type')) === 'checkbox') {
                $checkboxes++;
            }
        }

        if ($checkboxes === 0) {
            return false;
        }

        $text = mb_strtolower($form->textContent);

        foreach (self::CONSENT_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        // Текст согласия часто лежит в ссылке на политику, а не в самой форме.
        foreach ($form->getElementsByTagName('a') as $link) {
            if (str_contains(mb_strtolower($link->getAttribute('href')), 'polit')
                || str_contains(mb_strtolower($link->getAttribute('href')), 'privacy')) {
                return true;
            }
        }

        return false;
    }
}
