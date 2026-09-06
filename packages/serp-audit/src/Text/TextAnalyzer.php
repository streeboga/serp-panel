<?php

declare(strict_types=1);

namespace SerpAudit\Text;

final class TextAnalyzer
{
    /**
     * Окончания для примитивного стемминга, от длинных к коротким.
     *
     * ponytail: наивный стеммер — режет окончание, не разбирая часть речи. Для плотности
     * и совпадения ключей этого хватает (внешние аудиторы считают ещё грубее). Если
     * появятся жалобы на точность — менять на настоящую морфологию (phpMorphy/mystem).
     *
     * @var array<int, string>
     */
    private const ENDINGS = [
        'ования', 'ениями', 'ениях', 'аться', 'ается', 'ующий',
        'иями', 'ениям', 'ями', 'ами', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими',
        'ять', 'ить', 'ать', 'еть', 'уть', 'ешь', 'ишь',
        'ах', 'ях', 'ов', 'ев', 'ий', 'ый', 'ой', 'ей', 'ая', 'яя', 'ое', 'ее',
        'ые', 'ие', 'ом', 'ем', 'ью', 'ии', 'ия', 'ет', 'ит', 'ут', 'ют', 'ат',
        'ят', 'ла', 'ло', 'ли',
        'а', 'я', 'о', 'е', 'у', 'ю', 'ы', 'и', 'й', 'ь',
    ];

    private const MIN_STEM = 4;

    /** @return array<int, string> */
    public function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{Nd}]+/u', mb_strtolower($text), $matches);

        return $matches[0];
    }

    public function stem(string $word): string
    {
        if (mb_strlen($word) <= self::MIN_STEM) {
            return $word;
        }

        foreach (self::ENDINGS as $ending) {
            if (! str_ends_with($word, $ending)) {
                continue;
            }

            $stem = mb_substr($word, 0, mb_strlen($word) - mb_strlen($ending));

            if (mb_strlen($stem) >= self::MIN_STEM) {
                return $stem;
            }
        }

        return $word;
    }

    /**
     * @param  array<int, string>  $words
     * @return array<string, int> стем => количество, по убыванию
     */
    public function frequencies(array $words): array
    {
        $counts = [];

        foreach ($words as $word) {
            if (StopWords::contains($word)) {
                continue;
            }

            $stem = $this->stem($word);
            $counts[$stem] = ($counts[$stem] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /** @param array<int, string> $words */
    public function waterPercent(array $words): float
    {
        if ($words === []) {
            return 0.0;
        }

        $stop = 0;

        foreach ($words as $word) {
            if (StopWords::contains($word)) {
                $stop++;
            }
        }

        return round($stop / count($words) * 100, 2);
    }

    /**
     * Классическая тошнота — корень из числа повторов самого частого слова.
     *
     * @param  array<string, int>  $frequencies
     */
    public function classicNausea(array $frequencies): float
    {
        $top = $frequencies === [] ? 0 : (int) reset($frequencies);

        return round(sqrt($top), 2);
    }

    /**
     * Академическая тошнота — доля повторяющихся слов в общем объёме текста.
     * Приближение общепринятой формулы: точная методика у каждого сервиса своя.
     *
     * @param  array<string, int>  $frequencies
     */
    public function academicNausea(array $frequencies, int $totalWords): float
    {
        if ($totalWords === 0) {
            return 0.0;
        }

        $repeated = array_sum(array_filter($frequencies, static fn (int $count): bool => $count >= 2));

        return round($repeated / $totalWords * 100, 2);
    }

    /**
     * Плотность ключевых слов в процентах.
     *
     * @param  array<string, int>  $frequencies
     * @return array<string, float>
     */
    public function density(array $frequencies, int $totalWords, int $limit = 20): array
    {
        if ($totalWords === 0) {
            return [];
        }

        $density = [];

        foreach (array_slice($frequencies, 0, $limit, true) as $stem => $count) {
            $density[$stem] = round($count / $totalWords * 100, 2);
        }

        return $density;
    }

    /**
     * Индекс удобочитаемости Флеша в адаптации Оборневой для русского языка:
     * коэффициенты у русского другие, английские дают заниженный результат
     * из-за более длинных слов.
     *
     * 100 — предельно просто, 0 — научная статья. Ниже 30 текст тяжёлый.
     *
     * @return array{score: float|null, sentences: int, words_per_sentence: float, syllables_per_word: float}
     */
    public function readability(string $text): array
    {
        $sentences = preg_split('/[.!?…]+(?:\s|$)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sentences = array_values(array_filter($sentences, static fn (string $s): bool => trim($s) !== ''));
        $words = $this->words($text);

        // На коротком тексте формула шумит: скажем «не проверено», а не соврём.
        if (count($sentences) < 3 || count($words) < 100) {
            return ['score' => null, 'sentences' => count($sentences), 'words_per_sentence' => 0.0, 'syllables_per_word' => 0.0];
        }

        $syllables = 0;

        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[аеёиоуыэюяaeiouy]/u', $word));
        }

        $wordsPerSentence = count($words) / count($sentences);
        $syllablesPerWord = $syllables / count($words);

        return [
            'score' => round(206.835 - 1.3 * $wordsPerSentence - 60.1 * $syllablesPerWord, 1),
            'sentences' => count($sentences),
            'words_per_sentence' => round($wordsPerSentence, 1),
            'syllables_per_word' => round($syllablesPerWord, 2),
        ];
    }

    /** Все ли значимые слова фразы встречаются в тексте (сравнение по стемам). */
    public function phraseCoverage(string $phrase, string $haystack): float
    {
        $needles = array_values(array_filter(
            $this->words($phrase),
            static fn (string $word): bool => ! StopWords::contains($word),
        ));

        if ($needles === []) {
            return 0.0;
        }

        $stems = array_map($this->stem(...), $this->words($haystack));
        $stems = array_fill_keys($stems, true);

        $found = 0;

        foreach ($needles as $needle) {
            if (isset($stems[$this->stem($needle)])) {
                $found++;
            }
        }

        return round($found / count($needles) * 100, 2);
    }
}
