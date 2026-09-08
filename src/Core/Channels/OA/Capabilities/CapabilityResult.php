<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

final readonly class CapabilityResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public bool $available,
        public mixed $value = null,
        public ?int $remain = null,
        public ?int $limit = null,
        public array $meta = [],
        public string $source = 'live',
        public string $hint = '',
    ) {}

    /**
     * @return array{
     *     key: string,
     *     available: bool,
     *     value: mixed,
     *     remain: int|null,
     *     limit: int|null,
     *     meta: array<string, mixed>,
     *     source: string,
     *     hint: string
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'available' => $this->available,
            'value' => $this->value,
            'remain' => $this->remain,
            'limit' => $this->limit,
            'meta' => $this->meta,
            'source' => $this->source,
            'hint' => $this->hint,
        ];
    }
}
