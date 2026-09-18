<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

interface CapabilityChecker
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function check(OaSnapshot $snapshot, array $options = []): CapabilityResult;
}
