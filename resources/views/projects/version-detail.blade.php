<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.show', $project) }}" class="text-gray-500 hover:text-gray-700">← 버전 목록</a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">{{ $project->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $version->version_label }} · {{ $project->company_name }} → {{ $project->hospital_name }}</p>
                </div>
            </div>
            <a href="{{ route('projects.versions.create', $project) }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + 새 버전 추가
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-3 gap-6">

                {{-- 왼쪽: 버전 네비게이션 --}}
                <div class="col-span-1">
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b text-sm font-medium text-gray-700">버전 이력</div>
                        @foreach($versions as $v)
                            <a href="{{ route('projects.versions.show', [$project, $v]) }}"
                               class="flex items-center gap-3 px-4 py-3 border-b last:border-0 hover:bg-gray-50 {{ $v->id === $version->id ? 'bg-blue-50 border-l-4 border-l-blue-500' : '' }}">
                                <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                    v{{ $v->version_number }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">{{ $v->version_label }}</div>
                                    <div class="text-xs text-gray-400">{{ $v->created_at->format('m/d H:i') }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- 오른쪽: AI 검토 결과 --}}
                <div class="col-span-2 space-y-4">

                    @if($version->hasAiFeedback())
                        @php $feedback = $version->ai_feedback; @endphp

                        {{-- 리스크 레벨 --}}
                        @php
                            $riskConfig = [
                                'HIGH'   => ['color' => 'red',    'label' => '고위험', 'bg' => 'bg-red-50 border-red-200',    'text' => 'text-red-800',    'badge' => 'bg-red-100 text-red-800'],
                                'MEDIUM' => ['color' => 'yellow', 'label' => '중간 위험', 'bg' => 'bg-yellow-50 border-yellow-200', 'text' => 'text-yellow-800', 'badge' => 'bg-yellow-100 text-yellow-800'],
                                'LOW'    => ['color' => 'green',  'label' => '저위험', 'bg' => 'bg-green-50 border-green-200',  'text' => 'text-green-800',  'badge' => 'bg-green-100 text-green-800'],
                            ];
                            $risk = $riskConfig[$feedback['risk_level']] ?? $riskConfig['HIGH'];
                        @endphp

                        <div class="border rounded-lg p-5 {{ $risk['bg'] }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="px-3 py-1 rounded-full text-sm font-bold {{ $risk['badge'] }}">
                                        {{ $risk['label'] }}
                                    </span>
                                    <p class="mt-3 text-sm {{ $risk['text'] }}">{{ $feedback['summary'] ?? '' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- 누락 조항 --}}
                        @if(!empty($feedback['missing_clauses']))
                            <div class="bg-white rounded-lg shadow p-5">
                                <h3 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                                    <span class="w-5 h-5 bg-red-100 text-red-600 rounded text-xs flex items-center justify-center font-bold">!</span>
                                    누락 조항 ({{ count($feedback['missing_clauses']) }}개)
                                </h3>
                                <ul class="space-y-2">
                                    @foreach($feedback['missing_clauses'] as $clause)
                                        <li class="flex gap-2 text-sm text-gray-700">
                                            <span class="text-red-400 flex-shrink-0">•</span>
                                            {{ $clause }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- 오류 조항 --}}
                        @if(!empty($feedback['error_clauses']))
                            <div class="bg-white rounded-lg shadow p-5">
                                <h3 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                                    <span class="w-5 h-5 bg-orange-100 text-orange-600 rounded text-xs flex items-center justify-center font-bold">×</span>
                                    오류/위반 조항 ({{ count($feedback['error_clauses']) }}개)
                                </h3>
                                <ul class="space-y-2">
                                    @foreach($feedback['error_clauses'] as $clause)
                                        <li class="flex gap-2 text-sm text-gray-700">
                                            <span class="text-orange-400 flex-shrink-0">•</span>
                                            {{ $clause }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- 개선 제안 --}}
                        @if(!empty($feedback['suggestions']))
                            <div class="bg-white rounded-lg shadow p-5">
                                <h3 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                                    <span class="w-5 h-5 bg-blue-100 text-blue-600 rounded text-xs flex items-center justify-center font-bold">✓</span>
                                    개선 제안 ({{ count($feedback['suggestions']) }}개)
                                </h3>
                                <ul class="space-y-2">
                                    @foreach($feedback['suggestions'] as $suggestion)
                                        <li class="flex gap-2 text-sm text-gray-700">
                                            <span class="text-blue-400 flex-shrink-0">•</span>
                                            {{ $suggestion }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                    @else
                        <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">
                            AI 검토 결과가 없습니다
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
