<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    protected $fillable = [
        'academic_year_id',
        'week_number',
        'start_date',
        'end_date',
    ];
    protected $appends = ['start_end_date'];
    public function getStartEndDateAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return \Carbon\Carbon::parse($this->start_date)->format('d M') .
                ' to ' .
                \Carbon\Carbon::parse($this->end_date)->format('d M');
        }

        return null;
    }
}
