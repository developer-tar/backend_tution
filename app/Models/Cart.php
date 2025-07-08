<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'session_id', 'product_id','quantity', 'product_type'];

    public function course() {
        return $this->belongsTo(Course::class, 'product_id', 'id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
