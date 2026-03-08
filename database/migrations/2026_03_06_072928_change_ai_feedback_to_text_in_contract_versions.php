<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_versions', function (Blueprint $table) {
            // json → longtext로 변경 (암호화된 문자열 저장 가능하도록)
            $table->longText('ai_feedback')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contract_versions', function (Blueprint $table) {
            $table->json('ai_feedback')->nullable()->change();
        });
    }
};
