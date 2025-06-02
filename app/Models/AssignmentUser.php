<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentUser extends Model {
    protected $table = 'assignments_user';

    protected $fillable = [
        'course_user_id',
        'course_assignment_id',
        'is_completed',
        'completed_at',
    ];
}
