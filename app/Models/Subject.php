<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "name",
    ];

    public function courseTopics() {
        return $this->hasMany(CourseTopic::class, 'subject_id', 'id');
    }
}
