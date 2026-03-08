<?php

namespace App\Http\Controllers;

use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contractService
    ) {}

    // =============================================
    // 대시보드 (계약서 목록)
    // =============================================

    public function index()
    {
        $contracts = DB::table('contracts')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('contracts.index', compact('contracts'));
    }

    // =============================================
    // 계약서 작성 폼
    // =============================================

    public function create()
    {
        return view('contracts.create');
    }

    // =============================================
    // 계약서 제출 + AI 검토 실행
    // =============================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'company_name'  => 'required|string|max:255',
            'hospital_name' => 'required|string|max:255',
            'raw_content'   => 'required|string|min:50',
        ], [
            'title.required'         => '계약서 제목을 입력해주세요.',
            'company_name.required'  => '제약회사명을 입력해주세요.',
            'hospital_name.required' => '의료기관명을 입력해주세요.',
            'raw_content.required'   => '계약서 내용을 입력해주세요.',
            'raw_content.min'        => '계약서 내용은 최소 50자 이상이어야 합니다.',
        ]);

        $result = $this->contractService->reviewContract($validated);

        return redirect()
            ->route('contracts.show', $result['contract_id'])
            ->with('cached', $result['cached']);
    }

    // =============================================
    // 계약서 상세 + AI 검토 결과
    // =============================================

    public function show(int $id)
    {
        $contract = DB::table('contracts')->find($id);

        if (!$contract) {
            abort(404, '계약서를 찾을 수 없습니다.');
        }

        $aiFeedback = $contract->ai_feedback
            ? json_decode($contract->ai_feedback, true)
            : null;

        $statusHistory = DB::table('contract_status_history')
            ->where('contract_id', $id)
            ->orderBy('changed_at', 'asc')
            ->get();

        return view('contracts.show', compact('contract', 'aiFeedback', 'statusHistory'));
    }
}
