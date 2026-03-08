<?php

namespace App\Http\Controllers;

use App\Models\ContractProject;
use App\Models\ContractVersion;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContractProjectController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    public function __construct(private ContractService $service) {}

    // =============================================
    // 프로젝트 목록 (대시보드)
    // =============================================

    public function index()
    {
        $projects = ContractProject::where('user_id', Auth::id())
            ->with('latestVersion')
            ->orderByDesc('updated_at')
            ->get();

        $stats = [
            'total'          => $projects->count(),
            'needs_revision' => $projects->where('current_state', 'NEEDS_REVISION')->count(),
            'waiting_sign'   => $projects->where('current_state', 'WAITING_SIGN')->count(),
            'completed'      => $projects->where('current_state', 'COMPLETED')->count(),
        ];

        return view('projects.index', compact('projects', 'stats'));
    }

    // =============================================
    // 새 프로젝트 폼
    // =============================================

    public function create()
    {
        return view('projects.create');
    }

    // =============================================
    // 새 프로젝트 + 원본 버전 저장
    // =============================================

    public function store(Request $request)
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'company_name'  => 'required|string|max:255',
            'hospital_name' => 'required|string|max:255',
            'raw_content'   => 'required|string|min:10',
        ]);

        try {
            $result = $this->service->createProject(
                $request->only(['title', 'company_name', 'hospital_name', 'raw_content']),
                Auth::id()
            );

            return redirect()
                ->route('projects.versions.show', [
                    'project' => $result['project']->id,
                    'version' => $result['version']->id,
                ])
                ->with('success', 'AI 검토가 완료되었습니다.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'AI 검토 중 오류가 발생했습니다: ' . $e->getMessage()]);
        }
    }

    // =============================================
    // 프로젝트 상세 (버전 목록)
    // =============================================

    public function show(ContractProject $project)
    {
        $this->authorize('view', $project);

        $versions = $project->versions()->orderByDesc('version_number')->get();

        return view('projects.show', compact('project', 'versions'));
    }

    // =============================================
    // 새 버전 추가 폼
    // =============================================

    public function addVersionForm(ContractProject $project)
    {
        $this->authorize('view', $project);

        $latestVersion = $project->latestVersion;

        return view('projects.add-version', compact('project', 'latestVersion'));
    }

    // =============================================
    // 새 버전 저장
    // =============================================

    public function addVersion(Request $request, ContractProject $project)
    {
        $this->authorize('view', $project);

        $request->validate([
            'raw_content' => 'required|string|min:10',
            'memo'        => 'nullable|string|max:255',
        ]);

        try {
            $version = $this->service->addVersionToProject(
                $project,
                $request->raw_content,
                $request->memo
            );

            return redirect()
                ->route('projects.versions.show', [
                    'project' => $project->id,
                    'version' => $version->id,
                ])
                ->with('success', $project->nextVersionLabel() . ' AI 검토가 완료되었습니다.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'AI 검토 중 오류가 발생했습니다: ' . $e->getMessage()]);
        }
    }

    // =============================================
    // 특정 버전 상세 (AI 검토 결과)
    // =============================================

    public function showVersion(ContractProject $project, ContractVersion $version)
    {
        $this->authorize('view', $project);

        $versions = $project->versions()->orderBy('version_number')->get();

        return view('projects.version-detail', compact('project', 'version', 'versions'));
    }
}
