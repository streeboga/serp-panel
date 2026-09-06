<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Политика заглушения находок: объект «код находки → причина».
 *
 * Код находки это не код проверки. У проверки `content.nausea` находки
 * `content.nausea.academic`, `content.nausea.classic` и `content.nausea.density`,
 * и заглушить нужно бывает первые две, оставив третью. Поэтому отключение
 * проверки целиком (`check_codes`) заменой заглушке не является.
 */
final class MutedCodes
{
    /** Разрешённый вид кода: два и больше сегмента через точку. */
    public const PATTERN = '/^[a-z0-9]+(?:\.[a-z0-9_]+)+$/';

    /**
     * @param  array<array-key, mixed>|null  $policy
     * @return array<string, string>
     */
    public static function normalize(?array $policy): array
    {
        $normalized = [];

        foreach ($policy ?? [] as $code => $reason) {
            if (! is_string($code) || ! is_string($reason)) {
                continue;
            }

            $normalized[$code] = $reason;
        }

        return $normalized;
    }

    /**
     * Помечает заглушённые находки и считает их отдельно.
     *
     * Находка не выбрасывается: она остаётся в списке с полями `muted` и
     * `mute_reason`, чтобы «настоящих находок нет» и «мы перестали смотреть»
     * различались с одного взгляда.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @param  array<string, string>  $policy
     * @return array{findings: array<int, array<string, mixed>>, muted: int}
     */
    public static function apply(array $findings, array $policy): array
    {
        if ($policy === []) {
            return ['findings' => $findings, 'muted' => 0];
        }

        $muted = 0;
        $marked = [];

        foreach ($findings as $finding) {
            $code = is_string($finding['code'] ?? null) ? $finding['code'] : '';

            if (isset($policy[$code])) {
                $finding['muted'] = true;
                $finding['mute_reason'] = $policy[$code];
                $muted++;
            }

            $marked[] = $finding;
        }

        return ['findings' => $marked, 'muted' => $muted];
    }

    /** @param array<string, mixed> $finding */
    public static function isMuted(array $finding): bool
    {
        return ($finding['muted'] ?? false) === true;
    }
}
