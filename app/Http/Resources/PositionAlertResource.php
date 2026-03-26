<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PositionAlert;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin PositionAlert */
final class PositionAlertResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'position-alerts';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'keyword_id' => $this->keyword_id,
            'threshold_position' => $this->threshold_position,
            'direction' => $this->direction,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'is_active' => $this->is_active,
            'last_triggered_at' => $this->last_triggered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
        ];
    }
}
