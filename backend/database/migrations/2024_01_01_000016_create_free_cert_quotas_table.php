<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_cert_quotas', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->enum('type', ['increase', 'decrease', 'apply', 'cancel'])->index()->comment('类型:increase=增加,decrease=减少,apply=申请,cancel=取消');
            $table->unsignedBigInteger('order_id')->nullable()->index()->comment('订单ID');
            $table->integer('quota')->default(0)->comment('变更配额');
            $table->integer('quota_before')->default(0)->comment('变更前配额');
            $table->integer('quota_after')->default(0)->comment('变更后配额');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_cert_quotas');
    }
};
