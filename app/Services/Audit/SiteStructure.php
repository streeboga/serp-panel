<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Разборы, которые видны только на сайте целиком: анкор-лист, дубли из-за
 * параметров в адресе, почти совпадающие заголовки.
 *
 * Чистые функции над уже собранными данными — проверяются без базы и сети.
 */
final class SiteStructure
{
    /** Один и тот же анкор чаще этой доли — анкор-лист переспамлен. */
    private const ANCHOR_DOMINANCE = 0.6;

    /** Похожесть выше этой — заголовки считаем частичными дублями. */
    private const SIMILARITY = 0.85;

    /**
     * Анкор-лист по каждому адресу: разнообразие и переспам.
     *
     * @param  array<int, array{to_url: string, anchors: array<string, int>}>  $targets
     * @return array{spam: array<int, array<string, mixed>>, diversity: float}
     */
    public function anchors(array $targets): array
    {
        $spam = [];
        $ratios = [];

        foreach ($targets as $target) {
            $total = array_sum($target['anchors']);

            // На двух-трёх ссылках говорить о переспаме нечего.
            if ($total < 5) {
                continue;
            }

            arsort($target['anchors']);
            $top = (int) reset($target['anchors']);
            $share = $top / $total;
            $ratios[] = count($target['anchors']) / $total;

            if ($share >= self::ANCHOR_DOMINANCE) {
                $spam[] = [
                    'url' => $target['to_url'],
                    'анкор' => (string) array_key_first($target['anchors']),
                    'доля' => round($share * 100).'%',
                    'ссылок' => $total,
                ];
            }
        }

        return [
            'spam' => $spam,
            'diversity' => $ratios === [] ? 1.0 : round(array_sum($ratios) / count($ratios), 2),
        ];
    }

    /**
     * Адреса, отличающиеся только параметрами: страница одна, а в индексе их
     * столько же, сколько сочетаний фильтров.
     *
     * @param  array<int, string>  $urls
     * @return array<int, array{path: string, variants: array<int, string>, params: array<int, string>}>
     */
    public function parameterDuplicates(array $urls): array
    {
        $byPath = [];

        foreach ($urls as $url) {
            $parts = parse_url($url);
            $query = $parts['query'] ?? '';

            if ($query === '') {
                continue;
            }

            $path = ($parts['host'] ?? '').rtrim($parts['path'] ?? '/', '/');
            parse_str($query, $params);

            $byPath[$path]['urls'][] = $url;
            $byPath[$path]['params'] = array_unique([...($byPath[$path]['params'] ?? []), ...array_keys($params)]);
        }

        $duplicates = [];

        foreach ($byPath as $path => $group) {
            if (count($group['urls']) < 2) {
                continue;
            }

            $duplicates[] = [
                'path' => $path,
                'variants' => array_slice($group['urls'], 0, 8),
                'params' => array_values($group['params']),
            ];
        }

        return $duplicates;
    }

    /**
     * Почти совпадающие заголовки: полные дубли ловит SQL, а «Купить шины в Москве»
     * против «Купить шины в Москве недорого» — уже нет.
     *
     * @param  array<int, array{url: string, value: string}>  $rows
     * @return array<int, array{value: string, similar: array<int, string>}>
     */
    public function nearDuplicates(array $rows, int $limit = 15): array
    {
        $groups = [];
        $taken = [];

        foreach ($rows as $i => $row) {
            if (isset($taken[$i]) || mb_strlen($row['value']) < 15) {
                continue;
            }

            $similar = [];

            foreach ($rows as $j => $other) {
                if ($j <= $i || isset($taken[$j]) || $row['value'] === $other['value']) {
                    continue;
                }

                similar_text(mb_strtolower($row['value']), mb_strtolower($other['value']), $percent);

                if ($percent / 100 >= self::SIMILARITY) {
                    $similar[] = $other['url'];
                    $taken[$j] = true;
                }
            }

            if ($similar !== []) {
                $taken[$i] = true;
                $groups[] = ['value' => $row['value'], 'similar' => [$row['url'], ...$similar]];
            }

            if (count($groups) >= $limit) {
                break;
            }
        }

        return $groups;
    }
}
