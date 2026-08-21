<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Model;

class ZaloAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Table::name(Table::AUDIT_LOGS);
    }

    /** @param array<string, mixed> $changes */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        ?string $ip = null,
        ?string $actor = null,
    ): self {
        return self::create([
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip' => $ip ?? request()->ip(),
            'actor' => $actor,
        ]);
    }
}
