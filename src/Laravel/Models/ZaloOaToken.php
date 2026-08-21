<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use Carbon\CarbonInterface;
use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $oa_id
 * @property string               $access_token
 * @property string               $refresh_token
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $refresh_expires_at
 * @property int                  $failed_attempts
 */
class ZaloOaToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        // Dựa trên APP_KEY — đổi APP_KEY là mất toàn bộ token.
        'access_token'       => 'encrypted',
        'refresh_token'      => 'encrypted',
        'expires_at'         => 'datetime',
        'refresh_expires_at' => 'datetime',
        'last_refreshed_at'  => 'datetime',
        'failed_attempts'    => 'integer',
    ];

    public function getTable(): string
    {
        return Table::name(Table::OA_TOKENS);
    }

    public function oa(): BelongsTo
    {
        return $this->belongsTo(ZaloOa::class, 'oa_id');
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Hết hạn refresh = không thể tự cứu, phải authorize lại thủ công. */
    public function refreshExpired(): bool
    {
        return $this->refresh_expires_at !== null && $this->refresh_expires_at->isPast();
    }

    /** Số ngày còn lại trước khi refresh_token hết hạn. Âm = đã quá hạn. */
    public function daysUntilRotation(): ?int
    {
        if ($this->refresh_expires_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->refresh_expires_at, false);
    }
}
