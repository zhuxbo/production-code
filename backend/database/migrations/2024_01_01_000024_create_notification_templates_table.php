<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->comment('ID');
            $table->string('name', 100)->index()->comment('模板名称');
            $table->string('type', 50)->index()->comment('通知类型');
            $table->text('content')->nullable()->comment('模板内容');
            $table->text('variables')->nullable()->comment('变量说明');
            $table->text('example')->nullable()->comment('示例');
            $table->tinyInteger('status')->default(1)->index()->comment('状态:1=启用,0=禁用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
