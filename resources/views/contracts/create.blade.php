@extends('layouts.app')

@section('title', '계약서 검토 요청')

@section('content')

<div class="mb-6">
    <a href="{{ route('contracts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← 대시보드</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-2">계약서 검토 요청</h1>
    <p class="text-gray-500 text-sm mt-1">계약서 내용을 입력하면 AI가 법령 위반 여부를 자동으로 검토합니다.</p>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-8">
    <form method="POST" action="{{ route('contracts.store') }}">
        @csrf

        {{-- 기본 정보 --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">계약서 제목 *</label>
                <input type="text" name="title" value="{{ old('title') }}"
                       placeholder="예: 신약 A 임상 3상 PMS 위탁 계약"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-400 @enderror">
                @error('title')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">제약회사명 *</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}"
                       placeholder="예: (주)네조트 제약"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('company_name') border-red-400 @enderror">
                @error('company_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">의료기관명 *</label>
                <input type="text" name="hospital_name" value="{{ old('hospital_name') }}"
                       placeholder="예: 고베 종합병원"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('hospital_name') border-red-400 @enderror">
                @error('hospital_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- 계약서 본문 --}}
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">계약서 내용 *</label>
            <textarea name="raw_content" rows="14"
                      placeholder="계약서 내용을 여기에 붙여넣으세요.&#10;&#10;예시)&#10;제1조 (목적) 본 계약은 의약품 제조 판매 후 조사를 위탁하기 위한 목적으로 체결한다.&#10;제2조 (조사 기간) 조사 기간은 2025년 1월 1일부터 2027년 12월 31일까지로 한다.&#10;제3조 (보수) 증례당 위탁료는 30,000엔으로 하며, 보고서 제출 후 60일 이내 지급한다."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono @error('raw_content') border-red-400 @enderror">{{ old('raw_content') }}</textarea>
            @error('raw_content')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- AI 안내 --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-700">
            🤖 <strong>AI 검토 항목:</strong>
            보고 기한(15일 이내) · 개인정보 비식별화 조항 · 위탁료 지급 조건 · 계약 해지 조항 · 기밀유지 조항
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('contracts.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                취소
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 font-medium">
                🔍 AI 검토 시작
            </button>
        </div>
    </form>
</div>

@endsection
