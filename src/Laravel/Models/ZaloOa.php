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

    /**
     * Default ở tầng model, không chỉ ở migration.
     *
     * Migration có `default('default')` nhưng đó là default của DB — instance
     * vừa `create()` xong vẫn mang null cho tới khi refresh từ DB. Bất kỳ code
     * nào đọc `$oa->app_key` ngay sau khi tạo (Authorizer, ZaloManager) sẽ vỡ.
     */
    protected $attributes = [
        'app_key' => 'default',
        'is_active' => true,
    ];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    /** Prefix chỉ biết được lúc runtime — không hardcode $table. */
    /**
     * URL dùng slug, không dùng id.
     *
     * Bắt buộc phải khai: Route::bind() chỉ lo chiều URL -> model, còn
     * route('zalo.oas.test', $oa) sinh URL từ getRouteKey() vốn mặc định
     * trả về khoá chính. Thiếu dòng này thì link sinh ra là /oas/1/test
     * trong khi bind đi tra slug='1' -> 404 ở mọi nút bấm.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
