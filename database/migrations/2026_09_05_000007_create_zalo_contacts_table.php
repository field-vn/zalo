<?php

declare(strict_types=1);

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact OA đã từng follow / nhắn — phục vụ notifier (is_following, last_interaction_at).
 *
 * Package KHÔNG map số điện thoại ↔ zalo_user_id. oa_id là khoá nội bộ zl_oas.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::name(Table::CONTACTS), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('oa_id'); // FK zl_oas.id
            $table->string('zalo_user_id', 64);
            $table->boolean('is_following')->default(true);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_interaction_at');
            $table->timestamps();
            $table->unique(['oa_id', 'zalo_user_id']);
            $table->index(['oa_id', 'last_interaction_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::CONTACTS));
    }
};
