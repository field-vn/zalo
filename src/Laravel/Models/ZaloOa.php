<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $app_key
 * @property string $name
 * @property string $slug
 * @property string $oa_id
 * @property string|null $avatar_url
 * @property array<int, string> $tags
 * @property bool $is_active
 * @property ZaloOaToken|null $token
 */
class ZaloOa extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    /** Prefix chỉ biết được lúc runtime — không hardcode $table. */
    public function getTable(): string
    {
        return Table::name(Table::OAS);
    }

    /** @return HasOne<ZaloOaToken, $this> */
    public function token(): HasOne
    {
        return $this->hasOne(ZaloOaToken::class, 'oa_id');
    }

    /** @param Builder<ZaloOa> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Cấu hình App tương ứng — lấy từ config, không phải DB.
     *
     * @return array<string, mixed>|null
     */
    public function appConfig(): ?array
    {
        /** @var array<string, mixed>|null $config */
        $config = config('zalo.apps.'.$this->app_key);

        return $config;
    }

    public function isConnected(): bool
    {
        return $this->token !== null && ! $this->token->refreshExpired();
    }
}
