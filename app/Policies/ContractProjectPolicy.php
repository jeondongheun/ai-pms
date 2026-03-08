<?php

namespace App\Policies;

use App\Models\ContractProject;
use App\Models\User;

class ContractProjectPolicy
{
    /**
     * 자기 프로젝트만 볼 수 있음
     */
    public function view(User $user, ContractProject $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * 자기 프로젝트만 수정 가능
     */
    public function update(User $user, ContractProject $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * 자기 프로젝트만 삭제 가능
     */
    public function delete(User $user, ContractProject $project): bool
    {
        return $user->id === $project->user_id;
    }
}
