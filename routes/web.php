<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContractProjectController;
use Illuminate\Support\Facades\Route;

// 홈 → 로그인 상태면 프로젝트 목록, 아니면 로그인 페이지
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('projects.index')
        : redirect()->route('login');
});

// 대시보드 → 프로젝트 목록으로 리다이렉트
Route::get('/dashboard', function () {
    return redirect()->route('projects.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// =============================================
// 인증 필요 라우트
// =============================================

Route::middleware('auth')->group(function () {

    // 프로필 (Breeze 기본)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 계약 프로젝트 목록 (대시보드)
    Route::get('/projects', [ContractProjectController::class, 'index'])
        ->name('projects.index');

    // 새 프로젝트 생성
    Route::get('/projects/create', [ContractProjectController::class, 'create'])
        ->name('projects.create');
    Route::post('/projects', [ContractProjectController::class, 'store'])
        ->name('projects.store');

    // 프로젝트 상세 (버전 목록)
    Route::get('/projects/{project}', [ContractProjectController::class, 'show'])
        ->name('projects.show');

    // 새 버전 추가
    Route::get('/projects/{project}/versions/create', [ContractProjectController::class, 'addVersionForm'])
        ->name('projects.versions.create');
    Route::post('/projects/{project}/versions', [ContractProjectController::class, 'addVersion'])
        ->name('projects.versions.store');

    // 특정 버전 상세 (AI 검토 결과)
    Route::get('/projects/{project}/versions/{version}', [ContractProjectController::class, 'showVersion'])
        ->name('projects.versions.show');
});

require __DIR__.'/auth.php';
