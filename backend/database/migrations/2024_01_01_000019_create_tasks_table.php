<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index()->comment('关联ID(order_id 或 user_id)');
            $table->string('action', 50)->index()->comment('任务动作');
            $table->text('result')->nullable()->comment('执行结果');
            $table->integer('attempts')->default(0)->comment('执行次数');
            $table->timestamp('started_at')->nullable()->comment('开始执行时间');
            $table->timestamp('last_execute_at')->nullable()->comment('最后执行时间');
            $table->string('source', 50)->nullable()->comment('来源');
            $table->integer('weight')->default(0)->comment('权重');
            $table->enum('status', ['executing', 'successful', 'failed', 'stopped'])
                ->default('executing')
                ->index()
                ->comment('状态:executing=待执行,successful=已成功,failed=已失败,stopped=已停止');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
