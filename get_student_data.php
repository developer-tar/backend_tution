<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentDetail;

try {
    $students = StudentDetail::with(['student', 'year', 'month', 'day', 'region', 'gender', 'targetSchool'])
                            ->limit(2)
                            ->get();
    
    foreach ($students as $student) {
        echo "=== Student ID: {$student->id} ===\n";
        echo "Student Details:\n";
        echo "- ID: {$student->id}\n";
        echo "- Parent ID: {$student->parent_id}\n";
        echo "- Child ID: {$student->child_id}\n";
        echo "- Display Name: {$student->display_name}\n";
        echo "- Year ID: {$student->year_id} (Name: " . ($student->year->name ?? 'N/A') . ")\n";
        echo "- Month ID: {$student->month_id} (Name: " . ($student->month->name ?? 'N/A') . ")\n";
        echo "- Day ID: {$student->day_id} (Name: " . ($student->day->name ?? 'N/A') . ")\n";
        echo "- Region ID: {$student->region_id} (Name: " . ($student->region->name ?? 'N/A') . ")\n";
        echo "- Gender ID: {$student->gender_id} (Name: " . ($student->gender->name ?? 'N/A') . ")\n";
        echo "- Target School ID: {$student->target_school_id} (Name: " . ($student->targetSchool->name ?? 'N/A') . ")\n";
        echo "- Show Answer After N Attempts: {$student->show_answer_after_n_attempts}\n";
        echo "- Allow View Examiner Report: " . ($student->allow_view_examiner_report_for_mocks ? 'true' : 'false') . "\n";
        echo "- Can Change Password: " . ($student->can_change_password ? 'true' : 'false') . "\n";
        echo "- Bio: {$student->bio}\n";
        
        if ($student->student) {
            echo "User Details:\n";
            echo "- First Name: {$student->student->first_name}\n";
            echo "- Last Name: {$student->student->last_name}\n";
            echo "- Email: {$student->student->email}\n";
        }
        
        echo "\n--- UPDATE PAYLOAD ---\n";
        echo json_encode([
            'first_name' => $student->student->first_name ?? '',
            'last_name' => $student->student->last_name ?? '',
            'email' => $student->student->email ?? '',
            'year_id' => $student->year_id,
            'month_id' => $student->month_id,
            'day_id' => $student->day_id,
            'region_id' => $student->region_id,
            'gender_id' => $student->gender_id,
            'target_school_id' => $student->target_school_id,
            'display_name' => $student->display_name,
            'show_answer_after_n_attempts' => $student->show_answer_after_n_attempts,
            'allow_view_examiner_report_for_mocks' => $student->allow_view_examiner_report_for_mocks,
            'can_change_password' => $student->can_change_password,
            'bio' => $student->bio
        ], JSON_PRETTY_PRINT);
        echo "\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
