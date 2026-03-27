<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Enums\Engine;
use Illuminate\Foundation\Http\FormRequest;

final class StartIndexingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'engine' => 'sometimes|string|in:google,yandex',
            'limit' => 'sometimes|integer|min:10|max:1000',
        ];
    }

    public function engine(): Engine
    {
        return Engine::from($this->input('engine', 'google'));
    }

    public function limit(): int
    {
        return (int) $this->input('limit', 100);
    }
}
