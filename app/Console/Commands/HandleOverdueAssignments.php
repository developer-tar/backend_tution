<?php

namespace App\Console\Commands;

use App\Models\CourseAssignment;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\CourseSubTopic;
use App\Models\ManageStudentRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HandleOverdueAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assignments:handle-overdue {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark overdue assignments and tests as incomplete, prevent test attempts but allow content viewing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();
        
        $this->info('🔍 Checking for overdue assignments...');
        $this->info('Current time: ' . $now->format('Y-m-d H:i:s'));
        
        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }
        
        // Get all expired weeks
        $expiredAssignments = CourseAssignment::with(['weeks', 'acdemicCourses'])
            ->whereHas('weeks', function($query) use ($now) {
                $query->where('end_date', '<', $now);
            })
            ->get();
            
        $this->info("📋 Found {$expiredAssignments->count()} expired assignments");
        
        $totalProcessed = 0;
        $totalMarkedOverdue = 0;
        
        foreach ($expiredAssignments as $assignment) {
            $week = $assignment->weeks;
            $this->line("📅 Processing assignment for week: {$week->week_number} (ended: {$week->end_date})");
            
            // Get all students enrolled in this course
            $students = User::whereHas('course', function($query) use ($assignment) {
                $query->where('id', $assignment->acdemic_course_id);
            })->get();
            
            foreach ($students as $student) {
                $totalProcessed++;
                
                // Process Topics
                $topics = CourseTopic::where('course_assignment_id', $assignment->id)->get();
                foreach ($topics as $topic) {
                    $this->processTopicOverdue($topic, $student, $dryRun, $totalMarkedOverdue);
                }
                
                // Process SubTopics
                $subTopics = CourseSubTopic::whereHas('courseTopic', function($query) use ($assignment) {
                    $query->where('course_assignment_id', $assignment->id);
                })->get();
                
                foreach ($subTopics as $subTopic) {
                    $this->processSubTopicOverdue($subTopic, $student, $dryRun, $totalMarkedOverdue);
                }
                
                // Process Tests
                $tests = CourseTest::whereHas('courseTopic', function($query) use ($assignment) {
                    $query->where('course_assignment_id', $assignment->id);
                })->orWhereHas('courseSubTopic.courseTopic', function($query) use ($assignment) {
                    $query->where('course_assignment_id', $assignment->id);
                })->get();
                
                foreach ($tests as $test) {
                    $this->processTestOverdue($test, $student, $dryRun, $totalMarkedOverdue);
                }
            }
        }
        
        $this->newLine();
        $this->info("✅ Processing completed!");
        $this->info("📊 Students processed: {$totalProcessed}");
        $this->info("🔄 Records marked as overdue: {$totalMarkedOverdue}");
        
        if ($dryRun) {
            $this->warn('🧪 This was a dry run. Run without --dry-run to apply changes.');
        }
        
        return 0;
    }
    
    private function processTopicOverdue($topic, $student, $dryRun, &$totalMarkedOverdue)
    {
        // Check if student has record for this topic
        $record = ManageStudentRecord::where([
            'model_type' => 'App\\Models\\CourseTopic',
            'model_id' => $topic->id,
            'buyer_id' => $student->id
        ])->first();
        
        if (!$record) {
            // Create overdue record for topic content
            $this->line("  📝 Topic '{$topic->name}' - Creating overdue record for student: {$student->name}");
            
            if (!$dryRun) {
                ManageStudentRecord::create([
                    'model_type' => 'App\\Models\\CourseTopic',
                    'model_id' => $topic->id,
                    'buyer_id' => $student->id,
                    'is_completed' => config('constants.completed.NO'),
                    'is_overdue' => true,
                    'completed_at' => null
                ]);
            }
            $totalMarkedOverdue++;
        } elseif ($record->is_completed == config('constants.completed.NO')) {
            // Mark existing incomplete record as overdue
            $this->line("  📝 Topic '{$topic->name}' - Marking as overdue for student: {$student->name}");
            
            if (!$dryRun) {
                $record->update(['is_overdue' => true]);
            }
            $totalMarkedOverdue++;
        }
    }
    
    private function processSubTopicOverdue($subTopic, $student, $dryRun, &$totalMarkedOverdue)
    {
        $record = ManageStudentRecord::where([
            'model_type' => 'App\\Models\\CourseSubTopic',
            'model_id' => $subTopic->id,
            'buyer_id' => $student->id
        ])->first();
        
        if (!$record) {
            $this->line("  📝 SubTopic '{$subTopic->name}' - Creating overdue record for student: {$student->name}");
            
            if (!$dryRun) {
                ManageStudentRecord::create([
                    'model_type' => 'App\\Models\\CourseSubTopic',
                    'model_id' => $subTopic->id,
                    'buyer_id' => $student->id,
                    'is_completed' => config('constants.completed.NO'),
                    'is_overdue' => true,
                    'completed_at' => null
                ]);
            }
            $totalMarkedOverdue++;
        } elseif ($record->is_completed == config('constants.completed.NO')) {
            $this->line("  📝 SubTopic '{$subTopic->name}' - Marking as overdue for student: {$student->name}");
            
            if (!$dryRun) {
                $record->update(['is_overdue' => true]);
            }
            $totalMarkedOverdue++;
        }
    }
    
    private function processTestOverdue($test, $student, $dryRun, &$totalMarkedOverdue)
    {
        $record = ManageStudentRecord::where([
            'model_type' => 'App\\Models\\CourseTest',
            'model_id' => $test->id,
            'buyer_id' => $student->id
        ])->first();
        
        if (!$record) {
            $this->line("  🧪 Test '{$test->name}' - Creating overdue record for student: {$student->name}");
            
            if (!$dryRun) {
                ManageStudentRecord::create([
                    'model_type' => 'App\\Models\\CourseTest',
                    'model_id' => $test->id,
                    'buyer_id' => $student->id,
                    'is_completed' => config('constants.completed.NO'),
                    'is_overdue' => true,
                    'completed_at' => null
                ]);
            }
            $totalMarkedOverdue++;
        } elseif ($record->is_completed == config('constants.completed.NO')) {
            $this->line("  🧪 Test '{$test->name}' - Marking as overdue for student: {$student->name}");
            
            if (!$dryRun) {
                $record->update(['is_overdue' => true]);
            }
            $totalMarkedOverdue++;
        }
    }
}
