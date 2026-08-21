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
        Schema::create(Table::name(Table::OAS), function (Blueprint $table): void {
            $table->id();

            // Trỏ tới config('zalo.apps.*') — App credentials nằm ở env, không ở DB.
            $table->string('app_key', 64)->default('default');

            $table->string('name');
            $table->string('slug', 100);
            $table->string('oa_id', 64);
            $table->string('avatar_url')->nullable();
            $table->text('description')->nullable();
            $table->string('package_type', 64)->nullable();

            // Dùng cho phân phối có chủ đích: Zalo::oas(fn ($oa) => in_array('cskh', $oa->tags))
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Đặt tên index THỦ CÔNG — tên auto-generate cộng prefix dễ tràn
            // giới hạn 64 ký tự của MySQL.
            $table->unique('slug', 'zl_oas_slug_uq');
            $table->unique('oa_id', 'zl_oas_oaid_uq');
            $table->index(['is_active', 'deleted_at'], 'zl_oas_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::OAS));
    }
};
