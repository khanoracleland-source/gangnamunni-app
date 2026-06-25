<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
       Run the migrations.
    **/
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('사용자 ID (로그인 사용자용)');
            $table->string('session_id', 255)->nullable()->comment('세션 ID (게스트 사용자용)');
            $table->string('navigation_pc_mode', 20)->default('same-tab')->comment('PC 네비게이션 모드: same-tab, new-tab');
            $table->string('navigation_mobile_mode', 20)->default('same-page')->comment('모바일 네비게이션 모드: same-page, popup');
            $table->string('theme', 20)->default('light')->comment('테마: light, dark');
            $table->string('font_size', 20)->default('medium')->comment('폰트 크기: small, medium, large');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('session_id');
            $table->unique(['session_id'], 'unique_session_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
