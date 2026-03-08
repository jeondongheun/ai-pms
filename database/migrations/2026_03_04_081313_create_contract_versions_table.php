<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_project_id')->constrained()->onDelete('cascade');

            // 버전 관리
            $table->unsignedInteger('version_number');  // 1, 2, 3...
            $table->string('version_label');            // 원본, 1차 수정본, 2차 수정본

            // 계약서 원문 (암호화 저장)
            $table->longText('raw_content');            // encrypted cast로 암호화
            $table->string('content_hash', 64);        // SHA-256 캐시용

            // AI 검토 결과
            $table->json('ai_feedback')->nullable();
            $table->enum('state', [
                'DRAFT',
                'AI_REVIEWING',
                'NEEDS_REVISION',
                'WAITING_SIGN',
                'COMPLETED',
            ])->default('DRAFT');

            // 버전 메모 (선택사항)
            $table->string('memo')->nullable();         // "보고 기한 조항 수정"

            $table->timestamps();

            // 같은 프로젝트 내 버전 번호 중복 방지
            $table->unique(['contract_project_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
    }
};
