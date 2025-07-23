<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PDO;

class Location extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "name",
    ];
    public function courses(){
        return $this->belongsToMany(Course::class, 'course_location','location_id', 'course_id');
    }
}
