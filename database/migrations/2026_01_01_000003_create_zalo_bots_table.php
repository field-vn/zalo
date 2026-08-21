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
        Schema::create(Table::name(Table::BOTS), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 100);

            // Token tĩnh dạng 123456:abcdef — không dính Zalo App, không refresh.
            $table->text('token');

            $table->string('username')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug', 'zl_bots_slug_uq');
            $table->index(['is_active', 'deleted_at'], 'zl_bots_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::BOTS));
    }
};
