<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo',
        'website',
        'status',
    ];

    public function mockExams()
    {
        return $this->hasMany(MockExam::class, 'school_id');
    }
}
