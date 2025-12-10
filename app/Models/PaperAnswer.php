<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_option_id',
        'status',
    ];

    public function option()
    {
        return $this->belongsTo(PaperOption::class, 'paper_option_id');
    }
}







