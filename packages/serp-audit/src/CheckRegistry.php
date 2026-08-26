<?php

declare(strict_types=1);

namespace SerpAudit;

use SerpAudit\Contracts\PageCheck;

/**
 * Каталог проверок. Пакеты наполняют его из своих сервис-провайдеров, приложение
 * только спрашивает: какие проверки вообще есть и какие гнать в этом прогоне.
 */
final class CheckRegistry
{
    /** @var array<string, PageCheck> код => проверка */
    private array $checks = [];

    public function register(PageCheck ...$checks): self
    {
        foreach ($checks as $check) {
            // Совпадение кодов — не «последний победил», а ошибка сборки:
            // два пакета молча затирали бы находки друг друга.
            if (isset($this->checks[$check->code()])) {
                throw new \LogicException(
                    "Проверка с кодом [{$check->code()}] уже зарегистрирована: "
                    .$this->checks[$check->code()]::class.' против '.$check::class
                );
            }

            $this->checks[$check->code()] = $check;
        }

        return $this;
    }

    public function has(string $code): bool
    {
        return isset($this->checks[$code]);
    }

    /** @return array<string, PageCheck> */
    public function all(): array
    {
        return $this->checks;
    }

    /** @return array<int, string> */
    public function categories(): array
    {
        $categories = array_values(array_unique(array_map(
            static fn (PageCheck $check): string => $check->category(),
            $this->checks,
        )));

        sort($categories);

        return $categories;
    }

    /** @return array<int, string> */
    public function codes(): array
    {
        return array_keys($this->checks);
    }

    /**
     * Проверки для прогона. Оба фильтра необязательны и складываются:
     * пустой выбор означает «всё, что зарегистрировано».
     *
     * @param  array<int, string>|null  $categories
     * @param  array<int, string>|null  $codes
     * @return array<int, PageCheck>
     */
    public function select(?array $categories = null, ?array $codes = null): array
    {
        $selected = $this->checks;

        if ($categories !== null && $categories !== []) {
            $selected = array_filter(
                $selected,
                static fn (PageCheck $check): bool => in_array($check->category(), $categories, true),
            );
        }

        if ($codes !== null && $codes !== []) {
            $selected = array_filter(
                $selected,
                static fn (PageCheck $check): bool => in_array($check->code(), $codes, true),
            );
        }

        return array_values($selected);
    }

    /**
     * Каталог для интерфейса: категории с их проверками.
     *
     * @return array<int, array{category: string, title: string, checks: array<int, array{code: string, title: string}>}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach ($this->categories() as $category) {
            $checks = array_values(array_filter(
                $this->checks,
                static fn (PageCheck $check): bool => $check->category() === $category,
            ));

            $catalog[] = [
                'category' => $category,
                'title' => Category::title($category),
                'checks' => array_map(static fn (PageCheck $check): array => [
                    'code' => $check->code(),
                    'title' => $check->title(),
                ], $checks),
            ];
        }

        return $catalog;
    }
}
