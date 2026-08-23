<?php

declare(strict_types=1);

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ai đã từng nhắn cho bot.
 *
 * Lý do tồn tại: `chat_id` là thứ bắt buộc để gửi tin, nhưng Zalo không có
 * API nào liệt kê nó. Cách duy nhất là bắt lấy lúc người ta nhắn tới —
 * `getUpdates` thì chỉ xem được một lần rồi mất, lại còn bị cấm khi đang cắm
 * webhook. Ghi xuống DB ngay khi webhook về là cách duy nhất để `chat_id`
 * còn dùng được về sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::name(Table::BOT_CHATS), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->constrained(Table::name(Table::BOTS))->cascadeOnDelete();

            // Zalo trả id dạng số rất lớn — lưu string để không tràn và không
            // mất chữ số khi PHP ép sang int trên hệ 32-bit.
            $table->string('chat_id');

            $table->string('display_name')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'chat_id'], 'zl_bot_chats_uq');
            $table->index('last_message_at', 'zl_bot_chats_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::BOT_CHATS));
    }
};
