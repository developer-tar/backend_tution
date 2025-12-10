<?php

namespace App\Policies;

use App\Models\MockExam;
use App\Models\User;

class MockExamPolicy
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
     * Determine if the user can view any mock exams.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can view the mock exam.
     */
    public function view(User $user, MockExam $mockExam): bool
    {
        // Admin can view all, users can view if they purchased it
        if ($this->isAdmin($user)) {
            return true;
        }

        return $mockExam->purchases()
            ->where('user_id', $user->id)
            ->where('payment_status', config('constants.stripe_payment_status.PAID'))
            ->exists();
    }

    /**
     * Determine if the user can create mock exams.
     */
    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can update the mock exam.
     */
    public function update(User $user, MockExam $mockExam): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can delete the mock exam.
     */
    public function delete(User $user, MockExam $mockExam): bool
    {
        // Admin can delete only if no purchases exist
        if (!$this->isAdmin($user)) {
            return false;
        }

        return !$mockExam->purchases()->withTrashed()->exists();
    }

    /**
     * Determine if the user can restore the mock exam.
     */
    public function restore(User $user, MockExam $mockExam): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can permanently delete the mock exam.
     */
    public function forceDelete(User $user, MockExam $mockExam): bool
    {
        return $this->isAdmin($user) && !$mockExam->purchases()->withTrashed()->exists();
    }
}

