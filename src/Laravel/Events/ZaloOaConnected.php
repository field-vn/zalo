<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/** OA vừa được cấp quyền thành công và đã có token. */
class ZaloOaConnected
{
    use Dispatchable;

    public function __construct(public readonly ZaloOa $oa)
    {
    }
}
