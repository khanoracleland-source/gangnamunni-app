<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fcm_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 191);
            $table->text('fcm_token');
            $table->string('platform', 20)->default('android');
            $table->string('app_version', 30)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'platform', 'is_active']);
        });

        Schema::create('mobile_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 200);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('priority', 10)->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('fcm_message_id', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'is_read', 'created_at']);
        });

        Schema::create('mobile_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->unique();
            $table->string('title_template', 200);
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_templates');
        Schema::dropIfExists('mobile_notifications');
        Schema::dropIfExists('fcm_devices');
    }
};
