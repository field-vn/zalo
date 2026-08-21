<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Events;

use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * OA mất kết nối và cần cấp quyền lại thủ công.
 *
 * Package không tự gửi cảnh báo — nó không thể đoán bạn muốn nhận qua Slack,
 * email hay hệ thống giám sát nào. Lắng nghe event này và tự xử lý.
 */
class ZaloOaDisconnected
{
    use Dispatchable;

    public function __construct(
        public readonly ZaloOa $oa,
        public readonly string $reason,
    ) {}
}
