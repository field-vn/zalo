<?php

declare(strict_types=1);

use FieldVn\Zalo\Support\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách khỏi bảng oas có chủ đích.
 *
 * Token bị ghi đè mỗi giờ khi refresh. Để chung sẽ làm updated_at của OA nhảy
 * liên tục — phá cache, nhiễu audit log, và khiến cột "sửa lần cuối" trên UI
 * trở nên vô nghĩa. Tách ra còn cho phép truncate sạch token khi rotate
 * APP_KEY mà vẫn giữ nguyên cấu hình OA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::name(Table::OA_TOKENS), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oa_id');

            // encrypted cast ở tầng model — đổi APP_KEY là mất, xem zalo:reencrypt
            $table->text('access_token');
            $table->text('refresh_token');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();

            $table->text('last_error')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);

            $table->timestamps();

            $table->unique('oa_id', 'zl_tok_oa_uq');
            $table->index('expires_at', 'zl_tok_exp_idx');
            $table->index('refresh_expires_at', 'zl_tok_rexp_idx');

            $table->foreign('oa_id', 'zl_tok_oa_fk')
                ->references('id')
                ->on(Table::name(Table::OAS))
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::OA_TOKENS));
    }
};
