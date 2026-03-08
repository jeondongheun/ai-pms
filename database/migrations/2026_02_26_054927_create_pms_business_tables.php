<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 1. 지식 베이스 테이블 (Tree 구조 & Full-text Index)
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // 트리 자료구조: 법령 계층 구조 관리
            $table->string('category'); // GPSP, 개인정보보호법 등
            $table->string('clause_number')->nullable(); // 조항 번호 (예: 제15조 1항)
            $table->text('content'); // 실제 법령 텍스트
            $table->timestamps();

            // 알고리즘 최적화: 키워드 기반 역색인 검색을 위한 Full-text 인덱스
            $table->fullText('content');
            $table->foreign('parent_id')->references('id')->on('knowledge_base')->onDelete('cascade');
        });

        // 2. 계약서 테이블 (Hash 알고리즘 & 상태 패턴 반영)
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company_name');
            $table->string('hospital_name');

            // 상태 패턴: 문자열이 아닌 현재 상태의 식별자 관리 (코드 가독성 및 확장성)
            $table->string('current_state')->default('DRAFT');

            // 무결성 알고리즘: SHA-256 해시를 저장하여 내용 변경 여부 O(1) 탐색
            $table->string('content_hash', 64)->nullable();

            $table->text('raw_content'); // 원본 텍스트
            $table->json('ai_feedback')->nullable(); // 분석 결과 데이터 구조화
            $table->timestamps();
        });

        // 3. 상태 전이 이력 테이블 (State Pattern History)
        Schema::create('contract_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->string('from_state');
            $table->string('to_state');
            $table->string('changed_by')->default('SYSTEM');
            $table->text('reason')->nullable(); // 변경 사유 (감사 추적용)
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('contract_status_history');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('knowledge_base');
    }
};
