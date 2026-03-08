<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 계약 프로젝트 기본 정보
            $table->string('title');                    // 신약 A 임상 PMS 위탁 계약
            $table->string('company_name');             // (주)네조트 제약
            $table->string('hospital_name');            // 고베 종합병원

            // 현재 상태 (최신 버전 기준)
            $table->enum('current_state', [
                'DRAFT',
                'AI_REVIEWING',
                'NEEDS_REVISION',
                'WAITING_SIGN',
                'COMPLETED',
            ])->default('DRAFT');

            $table->timestamps();
            $table->softDeletes(); // 소프트 딜리트
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_projects');
    }
};
