<?php

namespace App\Policies;

use App\Models\Paper;
use App\Models\User;

class PaperPolicy
{
    /**
     * Check if user has admin role.
     */
    private function isAdmin(User $user): bool
    {
        return $user->roles()
            ->where('name', config('constants.roles.ADMIN'))
            ->exists();
    }

    /**
     * Determine if the user can view any papers.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can view the paper.
     */
    public function view(User $user, Paper $paper): bool
    {
        // Admin can view all, users can view if they purchased it
        if ($this->isAdmin($user)) {
            return true;
        }

        return $paper->purchases()
            ->where('user_id', $user->id)
            ->where('payment_status', config('constants.stripe_payment_status.PAID'))
            ->exists();
    }

    /**
     * Determine if the user can create papers.
     */
    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can update the paper.
     */
    public function update(User $user, Paper $paper): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can delete the paper.
     */
    public function delete(User $user, Paper $paper): bool
    {
        // Admin can delete only if no purchases exist
        if (!$this->isAdmin($user)) {
            return false;
        }

        return !$paper->purchases()->withTrashed()->exists();
    }

    /**
     * Determine if the user can restore the paper.
     */
    public function restore(User $user, Paper $paper): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can permanently delete the paper.
     */
    public function forceDelete(User $user, Paper $paper): bool
    {
        return $this->isAdmin($user) && !$paper->purchases()->withTrashed()->exists();
    }
}







