<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'parent_id',
        'status',
    ];

    // Parent category
    public function parent()
    {
        return $this->belongsTo(MockExamCategory::class, 'parent_id');
    }

    // Child categories
    public function children()
    {
        return $this->hasMany(MockExamCategory::class, 'parent_id');
    }

    // All descendants recursively
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    // Mock exams in this category
    public function mockExams()
    {
        return $this->hasMany(MockExam::class, 'category_id');
    }

    // Papers in this category
    public function papers()
    {
        return $this->hasMany(Paper::class, 'category_id');
    }
}
