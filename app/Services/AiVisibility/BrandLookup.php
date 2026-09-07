<?php

declare(strict_types=1);

namespace App\Services\AiVisibility;

/**
 * Видимость бренда в ответах ИИ — то, что open-seo называет Brand Lookup.
 *
 * Задаём модели вопросы, которые задал бы покупатель, и считаем: сколько раз
 * упомянут наш бренд и конкуренты (доля голоса), какие адреса модель
 * приводит как источники и есть ли среди них наш сайт. Ответы модели —
 * данные, а не инструкции: их только читаем и считаем.
 */
final readonly class BrandLookup
{
    public function __construct(
        private AnthropicClient $client,
    ) {}

    public function available(): bool
    {
        return $this->client->enabled();
    }

    /**
     * @param  array<int, string>  $competitors
     * @param  array<int, string>  $prompts
     * @return array<string, mixed>
     */
    public function run(string $brand, string $domain, array $competitors, array $prompts): array
    {
        $brands = array_values(array_unique(array_filter([$brand, ...$competitors], static fn (string $b): bool => trim($b) !== '')));
        $mentions = array_fill_keys($brands, 0);
        $answers = [];
        $citations = [];
        $ourCitations = 0;
        $unanswered = 0;

        foreach ($prompts as $prompt) {
            $answer = $this->client->ask($this->wrap($prompt));

            if ($answer === null) {
                $unanswered++;
                $answers[] = ['prompt' => $prompt, 'answer' => null, 'mentions' => [], 'cites_us' => false];

                continue;
            }

            $found = [];

            foreach ($brands as $name) {
                if ($this->mentions($answer, $name)) {
                    $mentions[$name]++;
                    $found[] = $name;
                }
            }

            $urls = $this->urls($answer);
            $citesUs = false;

            foreach ($urls as $url) {
                $host = $this->host($url);
                $citations[$host] = ($citations[$host] ?? 0) + 1;

                if ($host === $this->host($domain)) {
                    $citesUs = true;
                }
            }

            $ourCitations += $citesUs ? 1 : 0;
            $answers[] = ['prompt' => $prompt, 'answer' => $answer, 'mentions' => $found, 'cites_us' => $citesUs];
        }

        arsort($citations);
        $total = array_sum($mentions);

        return [
            'model' => $this->client->model(),
            'prompts' => count($prompts),
            'unanswered' => $unanswered,
            'share_of_voice' => array_map(
                static fn (int $n): array => ['mentions' => $n, 'share' => $total > 0 ? round($n / $total * 100, 1) : null],
                $mentions,
            ),
            'our_citations' => $ourCitations,
            'cited_hosts' => array_slice($citations, 0, 20, true),
            'answers' => $answers,
        ];
    }

    private function wrap(string $prompt): string
    {
        return 'Ответь как поисковый ассистент, по существу и с названиями конкретных компаний, '
            ."брендов и сайтов, если они уместны. Где можешь — приведи ссылки на источники.\n\n".$prompt;
    }

    private function mentions(string $text, string $brand): bool
    {
        return (bool) preg_match('~(?<![\p{L}\p{N}])'.preg_quote($brand, '~').'(?![\p{L}\p{N}])~iu', $text);
    }

    /** @return array<int, string> */
    private function urls(string $text): array
    {
        preg_match_all('~https?://[^\s)\]>"\'«»]+~iu', $text, $m);

        return array_map(static fn (string $u): string => rtrim($u, '.,;:'), $m[0]);
    }

    private function host(string $url): string
    {
        $host = mb_strtolower((string) (parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST) ?? $url));

        return (string) preg_replace('~^www\.~', '', $host);
    }
}
