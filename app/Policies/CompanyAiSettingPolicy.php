<?php

namespace App\Policies;

use App\Models\CompanyAiSetting;
use App\Models\User;

class CompanyAiSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'branch_admin']);
    }

    public function view(User $user, CompanyAiSetting $setting): bool
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'branch_admin'])) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $user->company_id === (int) $setting->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'branch_admin']);
    }

    public function update(User $user, CompanyAiSetting $setting): bool
    {
        return $this->view($user, $setting);
    }

    public function delete(User $user, CompanyAiSetting $setting): bool
    {
        return $this->view($user, $setting) && $user->hasRole('super_admin');
    }
}
