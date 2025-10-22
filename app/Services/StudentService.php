<?php

namespace App\Services;

use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentService
{
    /**
     * Get student details for editing
     *
     * @param int $studentId
     * @param int $parentId
     * @return StudentDetail|null
     */
    public function getStudentForEdit($studentId, $parentId)
    {
        return StudentDetail::with([
            'student:id,first_name,last_name,email',
            'year:id,name',
            'month:id,name',
            'day:id,name',
            'region:id,name',
            'gender:id,name',
            'targetSchool:id,name'
        ])
        ->where('id', $studentId)
        ->where('parent_id', $parentId)
        ->first();
    }

    /**
     * Update student details
     *
     * @param int $studentId
     * @param int $parentId
     * @param array $userData
     * @param array $studentData
     * @return StudentDetail
     * @throws \Exception
     */
    public function updateStudent($studentId, $parentId, $userData, $studentData)
    {
        DB::beginTransaction();
        
        try {
            // Find student detail record
            $studentDetail = StudentDetail::where('id', $studentId)
                                        ->where('parent_id', $parentId)
                                        ->first();
            
            if (!$studentDetail) {
                throw new \Exception('Student not found or access denied.');
            }

            // Update user details (excluding password)
            $user = User::find($studentDetail->child_id);
            if (!$user) {
                throw new \Exception('Student user record not found.');
            }

            $user->update($userData);

            // Update student details
            $studentDetail->update($studentData);

            DB::commit();
            
            // Return updated student with relationships
            return $this->getStudentForEdit($studentId, $parentId);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            throw $e;
        }
    }

    /**
     * Check if student exists and belongs to parent
     *
     * @param int $studentId
     * @param int $parentId
     * @return bool
     */
    public function studentBelongsToParent($studentId, $parentId)
    {
        return StudentDetail::where('id', $studentId)
                          ->where('parent_id', $parentId)
                          ->exists();
    }
}
