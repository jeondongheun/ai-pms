<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVersion extends Model
{
    protected $fillable = [
        'contract_project_id',
        'version_number',
        'version_label',
        'raw_content',
        'content_hash',
        'ai_feedback',
        'state',
        'memo',
    ];

    protected $casts = [
        'raw_content' => 'encrypted',
        'ai_feedback' => 'encrypted:array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContractProject::class, 'contract_project_id');
    }

    public function stateLabel(): string
    {
        return match($this->state) {
            'DRAFT'          => '초안',
            'AI_REVIEWING'   => 'AI 검토 중',
            'NEEDS_REVISION' => '수정 필요',
            'WAITING_SIGN'   => '서명 대기',
            'COMPLETED'      => '완료',
            default          => '알 수 없음',
        };
    }

    public function riskLabel(): string
    {
        $level = $this->ai_feedback['risk_level'] ?? 'UNKNOWN';
        return match($level) {
            'HIGH'   => '고위험',
            'MEDIUM' => '중간 위험',
            'LOW'    => '저위험',
            default  => '알 수 없음',
        };
    }

    public function riskColor(): string
    {
        $level = $this->ai_feedback['risk_level'] ?? 'UNKNOWN';
        return match($level) {
            'HIGH'   => 'red',
            'MEDIUM' => 'yellow',
            'LOW'    => 'green',
            default  => 'gray',
        };
    }

    public function hasAiFeedback(): bool
    {
        return !empty($this->ai_feedback) &&
               isset($this->ai_feedback['risk_level']) &&
               $this->ai_feedback['risk_level'] !== 'UNKNOWN';
    }
}
