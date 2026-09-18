<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

final readonly class CapabilityReport
{
    /**
     * @param  array<string, CapabilityResult>  $items
     * @param  array<string, mixed>  $package
     */
    public function __construct(
        public array $items,
        public array $package = [],
    ) {}

    public function get(string|OaCapability $key): ?CapabilityResult
    {
        $name = $key instanceof OaCapability ? $key->value : $key;

        return $this->items[$name] ?? null;
    }

    public function available(string|OaCapability $key): bool
    {
        $item = $this->get($key);

        return $item !== null && $item->available;
    }

    /**
     * @return array{
     *     package: array<string, mixed>,
     *     items: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'items' => array_values(array_map(
                static fn (CapabilityResult $item): array => $item->toArray(),
                $this->items,
            )),
        ];
    }
}
