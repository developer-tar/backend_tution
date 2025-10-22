<?php

namespace App\Services;

use App\Models\StudentDetail;
use App\Models\User;
use App\Models\ManageStudentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

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
     * Delete student and related records
     *
     * @param StudentDetail $student
     * @return bool
     * @throws \Exception
     */
    public function deleteStudent(StudentDetail $student)
    {
        DB::beginTransaction();
        
        try {
            $studentId = $student->id;
            $childId = $student->child_id;
            
            // Soft delete from manage_student_records table where buyer_id matches child_id
            ManageStudentRecord::where('buyer_id', $childId)->delete(); // This will be soft delete now
            
            // Soft delete student detail record (keeps record but marks as deleted)
            $student->delete(); // This will be soft delete because model uses SoftDeletes trait
            
            // Soft delete user record (keeps record but marks as deleted)
            $user = User::find($childId);
            if ($user) {
                $user->delete(); // This will be soft delete because User model now uses SoftDeletes trait
            }
            
            DB::commit();
            
            Log::info("Student deleted successfully. Student ID: {$studentId}, Child ID: {$childId}");
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            throw $e;
        }
    }

    /**
     * Get student emails for authenticated parent
     *
     * @param int $parentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStudentEmails($parentId)
    {
        return StudentDetail::with(['student:id,email'])
                          ->where('parent_id', $parentId)
                          ->get()
                          ->map(function ($studentDetail) {
                              return [
                                  'id' => $studentDetail->student->id, // User table ID
                                  'email' => $studentDetail->student->email
                              ];
                          });
    }

    /**
     * Reset student password
     *
     * @param User $user
     * @param string $newPassword
     * @return bool
     * @throws \Exception
     */
    public function resetStudentPassword(User $user, $newPassword)
    {
        DB::beginTransaction();
        
        try {
            // Update password
            $user->update([
                'password' => Hash::make($newPassword)
            ]);
            
            DB::commit();
            
            Log::info("Student password reset successfully. User ID: {$user->id}, Email: {$user->email}");
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to reset student password. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
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
