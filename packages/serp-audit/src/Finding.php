<?php

declare(strict_types=1);

namespace SerpAudit;

use SerpAudit\Contracts\PageCheck;

final readonly class Finding
{
    /**
     * @param  string  $check  код проверки — по нему её включают и выключают
     * @param  string  $code  код конкретного дефекта, например `meta.title.long`
     * @param  string  $category  категория из Category или своя
     */
    public function __construct(
        public string $check,
        public string $code,
        public string $category,
        public Severity $severity,
        public string $message,
        public mixed $value = null,
        public mixed $expected = null,
    ) {}

    /**
     * Находка постраничной проверки: код и категорию берём у неё самой, а код
     * дефекта из неё выводим — дублировать их руками значит рано или поздно
     * развести находку с каталожной записью.
     */
    public static function from(
        PageCheck $check,
        string $issue,
        Severity $severity,
        string $message,
        mixed $value = null,
        mixed $expected = null,
    ): self {
        return new self(
            check: $check->code(),
            code: $check->code().'.'.$issue,
            category: $check->category(),
            severity: $severity,
            message: $message,
            value: $value,
            expected: $expected,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'code' => $this->code,
            'category' => $this->category,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'value' => $this->value,
            'expected' => $this->expected,
        ];
    }
}
