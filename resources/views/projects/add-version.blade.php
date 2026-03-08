<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('projects.show', $project) }}" class="text-gray-500 hover:text-gray-700">← 버전 목록</a>
            <div>
                <h2 class="font-semibold text-xl text-gray-800">새 버전 추가</h2>
                <p class="text-sm text-gray-500">{{ $project->title }} · {{ $project->nextVersionLabel() }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-6">

                {{-- 왼쪽: 이전 버전 원문 (참고용) --}}
                @if($latestVersion)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-medium text-gray-900 mb-3">
                            이전 버전 원문
                            <span class="text-sm font-normal text-gray-500">({{ $latestVersion->version_label }})</span>
                        </h3>
                        <pre class="text-xs text-gray-600 bg-gray-50 rounded p-3 overflow-y-auto h-96 whitespace-pre-wrap font-mono">{{ $latestVersion->raw_content }}</pre>
                    </div>
                @endif

                {{-- 오른쪽: 새 버전 입력 --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-medium text-gray-900 mb-3">
                        수정된 계약서
                        <span class="text-sm font-normal text-blue-600">({{ $project->nextVersionLabel() }})</span>
                    </h3>

                    <form method="POST" action="{{ route('projects.versions.store', $project) }}">
                        @csrf

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">수정 내용 메모 (선택)</label>
                            <input type="text" name="memo" value="{{ old('memo') }}"
                                   placeholder="예: 이상사례 보고 기한 조항 수정"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">수정된 계약서 원문 *</label>
                            <textarea name="raw_content" rows="14"
                                      placeholder="수정된 계약서 내용을 붙여넣어 주세요..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      required>{{ old('raw_content') }}</textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('projects.show', $project) }}"
                               class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                취소
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                                AI 재검토 시작
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
