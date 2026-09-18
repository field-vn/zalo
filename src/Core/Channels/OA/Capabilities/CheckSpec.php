<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

/** Một lần check: key + options (template_id, user_id, …). */
final readonly class CheckSpec
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $key,
        public array $options = [],
    ) {}
}
