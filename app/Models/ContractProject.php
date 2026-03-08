<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'company_name',
        'hospital_name',
        'current_state',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class)->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ContractVersion::class)->latestOfMany('version_number');
    }

    public function nextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }

    public function nextVersionLabel(): string
    {
        $next = $this->nextVersionNumber();
        if ($next === 1) return '원본';
        return ($next - 1) . '차 수정본';
    }

    public function stateLabel(): string
    {
        return match($this->current_state) {
            'DRAFT'          => '초안',
            'AI_REVIEWING'   => 'AI 검토 중',
            'NEEDS_REVISION' => '수정 필요',
            'WAITING_SIGN'   => '서명 대기',
            'COMPLETED'      => '완료',
            default          => '알 수 없음',
        };
    }
}
