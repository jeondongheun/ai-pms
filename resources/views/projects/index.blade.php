<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                계약 프로젝트 목록
            </h2>
            <a href="{{ route('projects.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + 새 계약서 검토
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- 통계 카드 --}}
            <div class="grid grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">전체 프로젝트</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['needs_revision'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">수정 필요</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-2xl font-bold text-yellow-600">{{ $stats['waiting_sign'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">서명 대기</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">완료</div>
                </div>
            </div>

            {{-- 성공 메시지 --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            {{-- 프로젝트 목록 --}}
            @if($projects->isEmpty())
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <div class="text-gray-400 text-lg mb-4">아직 계약서가 없습니다</div>
                    <a href="{{ route('projects.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg text-sm font-medium">
                        첫 번째 계약서 검토 시작
                    </a>
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">계약명</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">제약회사</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">의료기관</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">최신 버전</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">수정일</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($projects as $project)
                                <tr class="hover:bg-gray-50 cursor-pointer"
                                    onclick="location.href='{{ route('projects.show', $project) }}'">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $project->title }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $project->company_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $project->hospital_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $project->latestVersion?->version_label ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $stateColors = [
                                                'DRAFT'          => 'bg-gray-100 text-gray-700',
                                                'AI_REVIEWING'   => 'bg-blue-100 text-blue-700',
                                                'NEEDS_REVISION' => 'bg-red-100 text-red-700',
                                                'WAITING_SIGN'   => 'bg-yellow-100 text-yellow-700',
                                                'COMPLETED'      => 'bg-green-100 text-green-700',
                                            ];
                                            $color = $stateColors[$project->current_state] ?? 'bg-gray-100 text-gray-700';
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $color }}">
                                            {{ $project->stateLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $project->updated_at->format('Y-m-d') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
