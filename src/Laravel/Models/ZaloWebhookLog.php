<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Models;

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $oa_id
 * @property string $event_name
 * @property string|null $message_id
 * @property array<string, mixed> $payload
 * @property string $status
 */
class ZaloWebhookLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $attributes = [
        'status' => 'received',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Table::name(Table::WEBHOOK_LOGS);
    }
}
