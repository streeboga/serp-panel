<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Разбор графа внутренних ссылок: глубина от главной, входящие ссылки, сироты.
 *
 * Чистая арифметика над рёбрами — ни базы, ни сети, чтобы её можно было
 * проверить на выдуманном сайте из пяти страниц.
 */
final class LinkGraph
{
    /**
     * @param  array<int, array{id: int, url: string}>  $pages
     * @param  array<int, array{from: int, to: string, nofollow: bool}>  $edges
     * @return array{
     *     depth: array<int, int|null>,
     *     inbound: array<int, int>,
     *     orphans: array<int, int>,
     *     unreachable: array<int, int>,
     *     max_depth: int
     * }
     */
    public function analyse(array $pages, array $edges, string $rootUrl): array
    {
        // Адрес → id страницы. Ссылка ведёт на URL, а обходим мы страницы.
        $idByHash = [];

        foreach ($pages as $page) {
            $idByHash[sha1($page['url'])] = $page['id'];
        }

        $adjacency = [];
        $inbound = array_fill_keys(array_column($pages, 'id'), 0);

        foreach ($edges as $edge) {
            $target = $idByHash[$edge['to']] ?? null;

            // Ссылка наружу или на непроверенную страницу — в графе её нет.
            if ($target === null || $target === $edge['from']) {
                continue;
            }

            $adjacency[$edge['from']][$target] = true;

            // nofollow не передаёт вес, но входящей ссылкой остаётся: страница
            // достижима, и сиротой её называть неверно.
            $inbound[$target]++;
        }

        $rootId = $idByHash[sha1($rootUrl)] ?? $idByHash[sha1(rtrim($rootUrl, '/').'/')] ?? null;

        $depth = array_fill_keys(array_column($pages, 'id'), null);

        if ($rootId !== null) {
            $depth[$rootId] = 0;
            $queue = [$rootId];

            // Обход в ширину: первая встреча и есть кратчайший путь в кликах.
            while ($queue !== []) {
                $current = array_shift($queue);

                foreach (array_keys($adjacency[$current] ?? []) as $next) {
                    if ($depth[$next] !== null) {
                        continue;
                    }

                    $depth[$next] = $depth[$current] + 1;
                    $queue[] = $next;
                }
            }
        }

        $orphans = [];
        $unreachable = [];

        foreach ($pages as $page) {
            $id = $page['id'];

            if ($id === $rootId) {
                continue;
            }

            if ($inbound[$id] === 0) {
                $orphans[] = $id;
            }

            // Ссылки есть, а от главной не дойти — замкнутый островок.
            if ($depth[$id] === null && $inbound[$id] > 0) {
                $unreachable[] = $id;
            }
        }

        return [
            'depth' => $depth,
            'inbound' => $inbound,
            'orphans' => $orphans,
            'unreachable' => $unreachable,
            'max_depth' => max([0, ...array_filter($depth, static fn (?int $d): bool => $d !== null)]),
        ];
    }
}
