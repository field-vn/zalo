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
        Schema::create(Table::name(Table::AUDIT_LOGS), function (Blueprint $table): void {
            $table->id();

            // UI dùng basic auth với credential dùng chung, nên không xác định
            // được "ai" — ghi IP là thông tin thực tế nhất có được (ADR-0004).
            $table->string('actor')->nullable();
            $table->string('ip', 45)->nullable();

            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id'], 'zl_audit_subject_idx');
            $table->index('created_at', 'zl_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::name(Table::AUDIT_LOGS));
    }
};
