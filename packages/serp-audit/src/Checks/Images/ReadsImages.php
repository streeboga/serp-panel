<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Images;

use DOMElement;
use SerpAudit\PageContext;

trait ReadsImages
{
    /** @return array<int, array{url: string, alt: string|null, internal: bool, sized: bool}> */
    protected function images(PageContext $context): array
    {
        $images = [];

        foreach ($context->query('//img') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $source = $node->getAttribute('src') ?: $node->getAttribute('data-src');
            $url = $source === '' ? null : $context->absolute($source);

            $images[] = [
                'url' => $url ?? '',
                'alt' => $node->hasAttribute('alt') ? trim($node->getAttribute('alt')) : null,
                'internal' => $url === null || $context->isInternal($url),
                'sized' => ($node->hasAttribute('width') && $node->hasAttribute('height'))
                    || str_contains($node->getAttribute('style'), 'aspect-ratio'),
            ];
        }

        return $images;
    }
}
