<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\Bot\Resources;

use FieldVn\Zalo\Core\Http\PendingRequest;
use Illuminate\Support\Traits\Macroable;

abstract class Resource
{
    use Macroable;

    public function __construct(protected readonly PendingRequest $request)
    {
    }
}
