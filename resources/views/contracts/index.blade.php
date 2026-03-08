@extends('layouts.app')

@section('title', '계약서 대시보드')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">계약서 대시보드</h1>
    <p class="text-gray-500 text-sm mt-1">AI가 검토한 PMS 계약서 목록입니다.</p>
</div>

{{-- 상태별 통계 카드 --}}
@php
    $total      = $contracts->count();
    $highRisk   = $contracts->filter(fn($c) => $c->current_state === 'NEEDS_REVISION')->count();
    $waiting    = $contracts->filter(fn($c) => $c->current_state === 'WAITING_SIGN')->count();
    $completed  = $contracts->filter(fn($c) => $c->current_state === 'COMPLETED')->count();
@endphp

<div class="grid grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <div class="text-3xl font-bold text-gray-800">{{ $total }}</div>
        <div class="text-sm text-gray-500 mt-1">전체</div>
    </div>
    <div class="bg-white rounded-xl p-4 border border-red-200 text-center">
        <div class="text-3xl font-bold text-red-600">{{ $highRisk }}</div>
        <div class="text-sm text-gray-500 mt-1">수정 필요</div>
    </div>
    <div class="bg-white rounded-xl p-4 border border-yellow-200 text-center">
        <div class="text-3xl font-bold text-yellow-600">{{ $waiting }}</div>
        <div class="text-sm text-gray-500 mt-1">서명 대기</div>
    </div>
    <div class="bg-white rounded-xl p-4 border border-green-200 text-center">
        <div class="text-3xl font-bold text-green-600">{{ $completed }}</div>
        <div class="text-sm text-gray-500 mt-1">완료</div>
    </div>
</div>

{{-- 계약서 목록 --}}
@if($contracts->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <div class="text-5xl mb-4">📄</div>
        <p class="text-gray-500">아직 검토된 계약서가 없습니다.</p>
        <a href="{{ route('contracts.create') }}"
           class="inline-block mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            첫 번째 계약서 검토 요청
        </a>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 text-gray-600 font-medium">계약서명</th>
                    <th class="text-left px-6 py-3 text-gray-600 font-medium">제약회사</th>
                    <th class="text-left px-6 py-3 text-gray-600 font-medium">의료기관</th>
                    <th class="text-left px-6 py-3 text-gray-600 font-medium">상태</th>
                    <th class="text-left px-6 py-3 text-gray-600 font-medium">등록일</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($contracts as $contract)
                @php
                    $stateConfig = match($contract->current_state) {
                        'DRAFT'          => ['label' => '작성중',    'class' => 'bg-gray-100 text-gray-600'],
                        'AI_REVIEWING'   => ['label' => 'AI 검토중', 'class' => 'bg-blue-100 text-blue-600'],
                        'NEEDS_REVISION' => ['label' => '수정 필요', 'class' => 'bg-red-100 text-red-600'],
                        'WAITING_SIGN'   => ['label' => '서명 대기', 'class' => 'bg-yellow-100 text-yellow-600'],
                        'COMPLETED'      => ['label' => '완료',      'class' => 'bg-green-100 text-green-600'],
                        default          => ['label' => $contract->current_state, 'class' => 'bg-gray-100 text-gray-600'],
                    };
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-800">{{ $contract->title }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $contract->company_name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $contract->hospital_name }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $stateConfig['class'] }}">
                            {{ $stateConfig['label'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ \Carbon\Carbon::parse($contract->created_at)->format('Y-m-d') }}
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('contracts.show', $contract->id) }}"
                           class="text-blue-600 hover:underline text-xs">
                            결과 보기 →
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
