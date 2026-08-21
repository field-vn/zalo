<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $token
 * @property string|null $username
 * @property bool $is_active
 */
class ZaloBot extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'token' => 'encrypted',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return Table::name(Table::BOTS);
    }

    /** @param Builder<ZaloBot> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Che token khi hiển thị: 123456:abcdef → 123456:••••cdef */
    public function maskedToken(): string
    {
        $token = $this->token;
        $parts = explode(':', $token, 2);

        if (count($parts) !== 2) {
            return str_repeat('•', 8).substr($token, -4);
        }

        return $parts[0].':'.str_repeat('•', 8).substr($parts[1], -4);
    }
}
