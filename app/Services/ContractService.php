<?php

namespace App\Services;

use App\Models\ContractProject;
use App\Models\ContractVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ContractService
{
    private Client $httpClient;
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private string $ragServerUrl;

    public function __construct()
    {
        $this->httpClient   = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout'  => 60,
        ]);
        $this->apiKey       = env('ANTHROPIC_API_KEY');
        $this->model        = env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');
        $this->maxTokens    = (int) env('ANTHROPIC_MAX_TOKENS', 2000);
        $this->ragServerUrl = env('RAG_SERVER_URL', 'http://localhost:8001');
    }

    // =============================================
    // PUBLIC: 새 프로젝트 + 원본 버전 생성
    // =============================================

    public function createProject(array $input, int $userId): array
    {
        return DB::transaction(function () use ($input, $userId) {
            $project = ContractProject::create([
                'user_id'       => $userId,
                'title'         => $input['title'],
                'company_name'  => $input['company_name'],
                'hospital_name' => $input['hospital_name'],
                'current_state' => 'DRAFT',
            ]);

            $version = $this->addVersion($project, $input['raw_content'], '원본');

            return [
                'project' => $project->fresh(),
                'version' => $version,
            ];
        });
    }

    // =============================================
    // PUBLIC: 기존 프로젝트에 새 버전 추가
    // =============================================

    public function addVersionToProject(ContractProject $project, string $content, ?string $memo = null): ContractVersion
    {
        $label = $project->nextVersionLabel();
        return $this->addVersion($project, $content, $label, $memo);
    }

    // =============================================
    // PRIVATE: 버전 생성 + AI 검토 공통 로직
    // =============================================

    private function addVersion(ContractProject $project, string $content, string $label, ?string $memo = null): ContractVersion
    {
        $hash          = $this->generateHash($content);
        $versionNumber = $project->nextVersionNumber();

        // 동일 내용 캐시 확인 (유효한 AI 결과가 있는 경우만)
        $cached = ContractVersion::where('contract_project_id', $project->id)
            ->where('content_hash', $hash)
            ->whereNotNull('ai_feedback')
            ->where('state', '!=', 'DRAFT')
            ->first();

        if ($cached && $cached->hasAiFeedback()) {
            $version = ContractVersion::create([
                'contract_project_id' => $project->id,
                'version_number'      => $versionNumber,
                'version_label'       => $label . ' (캐시)',
                'raw_content'         => $content,
                'content_hash'        => $hash,
                'ai_feedback'         => $cached->ai_feedback,
                'state'               => $cached->state,
                'memo'                => $memo,
            ]);
            $project->update(['current_state' => $cached->state]);
            return $version;
        }

        // 새 버전 생성
        $version = ContractVersion::create([
            'contract_project_id' => $project->id,
            'version_number'      => $versionNumber,
            'version_label'       => $label,
            'raw_content'         => $content,
            'content_hash'        => $hash,
            'ai_feedback'         => null,
            'state'               => 'DRAFT',
            'memo'                => $memo,
        ]);

        $this->updateState($project, $version, 'AI_REVIEWING');

        // RAG 검색 + Claude API 호출
        $relevantLaws = $this->searchRelevantLaws($content);
        $aiFeedback   = $this->callClaudeAPI($content, $relevantLaws);

        // AI 실패 시 버전 삭제 (잘못된 캐시 방지 - P3)
        if (!isset($aiFeedback['risk_level']) || $aiFeedback['risk_level'] === 'UNKNOWN') {
            $version->delete();
            throw new \RuntimeException($aiFeedback['summary'] ?? 'AI 검토 실패');
        }

        $nextState = $aiFeedback['risk_level'] === 'LOW' ? 'WAITING_SIGN' : 'NEEDS_REVISION';
        $version->update([
            'ai_feedback' => $aiFeedback,
            'state'       => $nextState,
        ]);
        $this->updateState($project, $version, $nextState);

        return $version->fresh();
    }

    // =============================================
    // PRIVATE: 상태 업데이트
    // =============================================

    private function updateState(ContractProject $project, ContractVersion $version, string $state): void
    {
        $version->update(['state' => $state]);
        $project->update(['current_state' => $state]);
    }

    // =============================================
    // PRIVATE: SHA-256 해시
    // =============================================

    private function generateHash(string $content): string
    {
        return hash('sha256', trim($content));
    }

    // =============================================
    // PRIVATE: RAG - FastAPI 서버 호출
    // =============================================

    private function searchRelevantLaws(string $content): array
    {
        $queryText = mb_substr($content, 0, 500);

        try {
            $ragClient = new Client(['timeout' => 30]);
            $response  = $ragClient->post("{$this->ragServerUrl}/search", [
                'json' => ['query' => $queryText, 'top_k' => 5],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $laws = $body['results'] ?? [];

            if (empty($laws)) {
                return $this->searchRelevantLawsFallback($content);
            }

            return array_values(array_filter($laws, fn($law) => $law['rerank_score'] >= 0));

        } catch (\Exception $e) {
            Log::warning('RAG 서버 호출 실패, 폴백 사용: ' . $e->getMessage());
            return $this->searchRelevantLawsFallback($content);
        }
    }

    private function searchRelevantLawsFallback(string $content): array
    {
        $keywords = $this->extractKeywords($content);
        $results  = collect();

        foreach ($keywords as $keyword) {
            $rows = DB::table('knowledge_base')
                ->where('content', 'LIKE', "%{$keyword}%")
                ->orWhere('category', 'LIKE', "%{$keyword}%")
                ->get(['category', 'clause_number', 'content']);
            $results = $results->merge($rows);
        }

        return $results
            ->unique('clause_number')
            ->take(5)
            ->values()
            ->map(fn($row) => [
                'clause'       => $row->clause_number,
                'text'         => $row->content,
                'source'       => $row->category,
                'score'        => 0.5,
                'rerank_score' => 0.5,
                'vector_score' => 0.5,
            ])
            ->toArray();
    }

    private function extractKeywords(string $content): array
    {
        $domainKeywords = [
            '보고', '이상사례', '부작용', '기한', '15일',
            '개인정보', '비식별', '동의', '프라이버시',
            '위탁료', '보수', '지급', '계약기간',
            '해지', '기밀', '지식재산', '데이터', '보관',
            'GPSP', '성령', '감사', '조사기간',
        ];

        $found = [];
        foreach ($domainKeywords as $keyword) {
            if (mb_strpos($content, $keyword) !== false) {
                $found[] = $keyword;
            }
        }

        return empty($found) ? ['보고', '개인정보', '계약'] : $found;
    }

    // =============================================
    // PRIVATE: Claude API 호출
    // =============================================

    private function callClaudeAPI(string $contractContent, array $relevantLaws): array
    {
        $lawContext = $this->buildLawContext($relevantLaws);

        $systemPrompt = <<<PROMPT
당신은 일본 제약 법령(GPSP, GCP) 및 ICH E2A 전문 변호사이자 PMS 계약 검토 AI입니다.
아래 법령 기준을 참고하여 계약서를 분석하고, 반드시 JSON 형식으로만 응답하세요.

[참고 법령 - GPSP 성령 / GCP 성령 / ICH E2A 벡터 검색 결과]
{$lawContext}

응답 형식 (JSON만 출력, 마크다운 코드블록 없이):
{
  "risk_level": "HIGH|MEDIUM|LOW",
  "missing_clauses": ["누락된 조항 설명"],
  "error_clauses": ["오류/위반 조항 설명"],
  "suggestions": ["개선 제안"],
  "summary": "전체 검토 요약 (2~3문장)"
}
PROMPT;

        try {
            $response = $this->httpClient->post('/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system'     => $systemPrompt,
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "다음 계약서를 검토해주세요:\n\n{$contractContent}",
                    ]],
                ],
            ]);

            $body  = json_decode($response->getBody()->getContents(), true);
            $text  = $body['content'][0]['text'] ?? '{}';
            $clean = preg_replace('/^```json\s*/m', '', $text);
            $clean = preg_replace('/^```\s*/m', '', $clean);
            $clean = trim($clean);

            if (preg_match('/\{.*\}/s', $clean, $matches)) {
                $clean = $matches[0];
            }

            $result = json_decode($clean, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('JSON 파싱 실패: ' . $text);
            }

            return $result;

        } catch (RequestException $e) {
            return $this->fallbackResponse('API 호출 오류: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->fallbackResponse('오류: ' . $e->getMessage());
        }
    }

    private function buildLawContext(array $laws): string
    {
        if (empty($laws)) {
            return '관련 법령을 찾을 수 없습니다. 일반적인 PMS 계약 기준으로 검토하세요.';
        }

        return collect($laws)->map(function ($law) {
            $clause = $law['clause'] ?? '';
            $text   = $law['text']   ?? '';
            $source = $law['source'] ?? '';
            $score  = isset($law['rerank_score'])
                ? sprintf('(관련도: %.0f%%)', max(0, $law['rerank_score']) * 100)
                : '';
            return "[{$clause}] {$source} {$score}\n{$text}";
        })->implode("\n\n");
    }

    private function fallbackResponse(string $errorMessage): array
    {
        return [
            'risk_level'      => 'UNKNOWN',
            'missing_clauses' => [],
            'error_clauses'   => [],
            'suggestions'     => [],
            'summary'         => '검토 중 오류가 발생했습니다: ' . $errorMessage,
        ];
    }
}
