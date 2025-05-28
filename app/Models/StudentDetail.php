<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDetail extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'year_id',
        'month_id',
        'day_id',
        'region_id',
        'gender_id',
        'target_school_id',
        'display_name',
        'show_answer_after_n_attempts',
        'allow_view_examiner_report_for_mocks',
        'can_change_password',
        'bio',
        'child_id',
    ];
    protected $casts = [
        'show_answer_after_n_attempts' => 'integer',
        'allow_view_examiner_report_for_mocks' => 'boolean',
        'can_change_password' => 'boolean',
    ];
}
