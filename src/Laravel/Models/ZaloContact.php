<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Người dùng Zalo đã từng tương tác với một OA (follow / nhắn tin).
 *
 * Dùng cho OaNotifier: biết `is_following` và `last_interaction_at`.
 * Package không lưu số điện thoại — chỉ `zalo_user_id`.
 *
 * @property int $id
 * @property int $oa_id
 * @property string $zalo_user_id
 * @property bool $is_following
 * @property Carbon $first_seen_at
 * @property Carbon $last_interaction_at
 */
class ZaloContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_following' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Table::name(Table::CONTACTS);
    }

    /** @return BelongsTo<ZaloOa, $this> */
    public function oa(): BelongsTo
    {
        return $this->belongsTo(ZaloOa::class, 'oa_id');
    }
}
