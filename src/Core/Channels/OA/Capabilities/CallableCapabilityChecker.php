<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

final class CallableCapabilityChecker implements CapabilityChecker
{
    /**
     * @param  callable(OaSnapshot, array<string, mixed>): CapabilityResult  $callback
     */
    public function __construct(
        private readonly string $name,
        private $callback,
    ) {}

    public function key(): string
    {
        return $this->name;
    }

    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult
    {
        return ($this->callback)($snapshot, $options);
    }
}
