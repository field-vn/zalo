<?php

declare(strict_types=1);

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::name(Table::WEBHOOK_LOGS), function (Blueprint $table): void {
            $table->id();

            // oa_id của Zalo (chuỗi), KHÔNG phải khoá ngoại — webhook có thể
            // đến từ một OA chưa được thêm vào hệ thống, và log vẫn phải ghi
            // được để còn biết mà điều tra.
            $table->string('oa_id', 64)->nullable();

            $table->string('event_name', 100);
            $table->string('message_id', 100)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['oa_id', 'created_at'], 'zl_wh_oa_created_idx');
            $table->index('event_name', 'zl_wh_event_idx');

            // Chống xử lý trùng khi Zalo gửi lại cùng một tin.
            $table->index('message_id', 'zl_wh_msgid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::WEBHOOK_LOGS));
    }
};
