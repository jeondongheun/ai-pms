<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.index') }}" class="text-gray-500 hover:text-gray-700">← 목록</a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">{{ $project->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $project->company_name }} → {{ $project->hospital_name }}</p>
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

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            {{-- 버전 목록 --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="font-medium text-gray-900">버전 이력 ({{ $versions->count() }}개)</h3>
                </div>

                @forelse($versions as $version)
                    <div class="px-6 py-5 border-b last:border-0 hover:bg-gray-50 cursor-pointer"
                         onclick="location.href='{{ route('projects.versions.show', [$project, $version]) }}'">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                {{-- 버전 번호 배지 --}}
                                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-bold">
                                    v{{ $version->version_number }}
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $version->version_label }}</div>
                                    @if($version->memo)
                                        <div class="text-sm text-gray-500">{{ $version->memo }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400 mt-1">{{ $version->created_at->format('Y-m-d H:i') }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                {{-- 리스크 레벨 --}}
                                @if($version->hasAiFeedback())
                                    @php
                                        $riskColors = [
                                            'HIGH'   => 'bg-red-100 text-red-700',
                                            'MEDIUM' => 'bg-yellow-100 text-yellow-700',
                                            'LOW'    => 'bg-green-100 text-green-700',
                                        ];
                                        $level = $version->ai_feedback['risk_level'] ?? 'UNKNOWN';
                                        $riskColor = $riskColors[$level] ?? 'bg-gray-100 text-gray-700';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $riskColor }}">
                                        {{ $version->riskLabel() }}
                                    </span>
                                @endif

                                {{-- 상태 --}}
                                @php
                                    $stateColors = [
                                        'NEEDS_REVISION' => 'bg-red-100 text-red-700',
                                        'WAITING_SIGN'   => 'bg-yellow-100 text-yellow-700',
                                        'COMPLETED'      => 'bg-green-100 text-green-700',
                                        'DRAFT'          => 'bg-gray-100 text-gray-700',
                                    ];
                                    $stateColor = $stateColors[$version->state] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $stateColor }}">
                                    {{ $version->stateLabel() }}
                                </span>

                                <span class="text-gray-400">→</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center text-gray-400">
                        버전이 없습니다
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
