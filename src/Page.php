<?php

declare(strict_types=1);

namespace Agilis\Ads;

/**
 * One page of a list: the items in `data`, and `pagination` with `has_more` and `next_cursor`.
 */
class Page extends ApiResponse
{
    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $items = $this->data['data'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $out = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = $item;
            }
        }

        return $out;
    }

    public function hasMore(): bool
    {
        return ($this->pagination()['has_more'] ?? false) === true;
    }

    public function nextCursor(): ?string
    {
        $cursor = $this->pagination()['next_cursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(): array
    {
        $pagination = $this->data['pagination'] ?? [];

        /** @var array<string, mixed> */
        return is_array($pagination) ? $pagination : [];
    }
}
