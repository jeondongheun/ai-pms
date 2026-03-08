@extends('layouts.app')

@section('title', $contract->title)

@section('content')

<div class="mb-6">
    <a href="{{ route('contracts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← 대시보드</a>
    <div class="flex items-center justify-between mt-2">
        <h1 class="text-2xl font-bold text-gray-800">{{ $contract->title }}</h1>
        @if(session('cached'))
            <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">캐시된 결과</span>
        @endif
    </div>
    <p class="text-gray-500 text-sm mt-1">
        {{ $contract->company_name }} → {{ $contract->hospital_name }}
    </p>
</div>

<div class="grid grid-cols-3 gap-6">

    {{-- 좌측: AI 검토 결과 --}}
    <div class="col-span-2 space-y-4">

        @if($aiFeedback)

        {{-- 리스크 레벨 --}}
        @php
            $riskConfig = match($aiFeedback['risk_level'] ?? 'UNKNOWN') {
                'HIGH'    => ['label' => '⚠️ 고위험', 'class' => 'bg-red-50 border-red-300 text-red-700'],
                'MEDIUM'  => ['label' => '⚡ 중위험', 'class' => 'bg-yellow-50 border-yellow-300 text-yellow-700'],
                'LOW'     => ['label' => '✅ 저위험', 'class' => 'bg-green-50 border-green-300 text-green-700'],
                default   => ['label' => '❓ 알 수 없음', 'class' => 'bg-gray-50 border-gray-300 text-gray-700'],
            };
        @endphp
        <div class="rounded-xl border-2 p-5 {{ $riskConfig['class'] }}">
            <div class="text-lg font-bold">{{ $riskConfig['label'] }}</div>
            <p class="text-sm mt-1">{{ $aiFeedback['summary'] ?? '' }}</p>
        </div>

        {{-- 누락 조항 --}}
        @if(!empty($aiFeedback['missing_clauses']))
        <div class="bg-white rounded-xl border border-red-200 p-5">
            <h3 class="font-semibold text-red-700 mb-3">🚨 누락된 조항</h3>
            <ul class="space-y-2">
                @foreach($aiFeedback['missing_clauses'] as $item)
                <li class="flex items-start gap-2 text-sm text-gray-700">
                    <span class="text-red-500 mt-0.5">•</span>
                    <span>{{ $item }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- 오류 조항 --}}
        @if(!empty($aiFeedback['error_clauses']))
        <div class="bg-white rounded-xl border border-orange-200 p-5">
            <h3 class="font-semibold text-orange-700 mb-3">⚠️ 오류/위반 조항</h3>
            <ul class="space-y-2">
                @foreach($aiFeedback['error_clauses'] as $item)
                <li class="flex items-start gap-2 text-sm text-gray-700">
                    <span class="text-orange-500 mt-0.5">•</span>
                    <span>{{ $item }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- 개선 제안 --}}
        @if(!empty($aiFeedback['suggestions']))
        <div class="bg-white rounded-xl border border-blue-200 p-5">
            <h3 class="font-semibold text-blue-700 mb-3">💡 개선 제안</h3>
            <ul class="space-y-2">
                @foreach($aiFeedback['suggestions'] as $item)
                <li class="flex items-start gap-2 text-sm text-gray-700">
                    <span class="text-blue-500 mt-0.5">•</span>
                    <span>{{ $item }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @else
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400">
            AI 검토 결과가 없습니다.
        </div>
        @endif
    </div>

    {{-- 우측: 계약서 정보 + 상태 이력 --}}
    <div class="space-y-4">

        {{-- 계약서 정보 --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 mb-3">계약서 정보</h3>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-gray-500">상태</dt>
                    <dd class="font-medium text-gray-800">{{ $contract->current_state }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">등록일</dt>
                    <dd class="text-gray-800">
                        {{ \Carbon\Carbon::parse($contract->created_at)->format('Y-m-d H:i') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">무결성 해시</dt>
                    <dd class="text-gray-400 text-xs font-mono break-all">
                        {{ substr($contract->content_hash, 0, 16) }}...
                    </dd>
                </div>
            </dl>
        </div>

        {{-- 상태 이력 (State Pattern) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 mb-3">처리 이력</h3>
            <div class="space-y-3">
                @forelse($statusHistory as $history)
                <div class="flex items-start gap-3 text-sm">
                    <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 shrink-0"></div>
                    <div>
                        <div class="text-gray-800 font-medium">
                            {{ $history->from_state }} → {{ $history->to_state }}
                        </div>
                        <div class="text-gray-500 text-xs">{{ $history->reason }}</div>
                        <div class="text-gray-400 text-xs">
                            {{ \Carbon\Carbon::parse($history->changed_at)->format('H:i:s') }}
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-gray-400 text-sm">이력이 없습니다.</p>
                @endforelse
            </div>
        </div>

        {{-- 원문 보기 --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 mb-3">계약서 원문</h3>
            <div class="text-xs text-gray-600 font-mono bg-gray-50 rounded p-3 max-h-48 overflow-y-auto whitespace-pre-wrap">{{ $contract->raw_content }}</div>
        </div>

    </div>
</div>

@endsection
