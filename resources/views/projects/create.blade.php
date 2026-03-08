<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('projects.index') }}" class="text-gray-500 hover:text-gray-700">← 목록</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">새 계약서 검토</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-8">

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('projects.store') }}">
                    @csrf

                    {{-- 계약 기본 정보 --}}
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">계약 기본 정보</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">계약명 *</label>
                                <input type="text" name="title" value="{{ old('title') }}"
                                       placeholder="예: 신약 A 임상 PMS 위탁 계약"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">제약회사 *</label>
                                    <input type="text" name="company_name" value="{{ old('company_name') }}"
                                           placeholder="예: (주)네조트 제약"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">의료기관 *</label>
                                    <input type="text" name="hospital_name" value="{{ old('hospital_name') }}"
                                           placeholder="예: 고베 종합병원"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-6">

                    {{-- 계약서 원문 --}}
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">계약서 원문</h3>
                        <p class="text-sm text-gray-500 mb-3">계약서 전체 내용을 붙여넣어 주세요. AI가 GPSP/GCP/ICH E2A 기준으로 검토합니다.</p>
                        <textarea name="raw_content" rows="16"
                                  placeholder="계약서 내용을 붙여넣어 주세요..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  required>{{ old('raw_content') }}</textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('projects.index') }}"
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                            취소
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                            AI 검토 시작
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
