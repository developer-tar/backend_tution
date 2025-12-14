<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingInformation extends Model
{
    use SoftDeletes;

    protected $table = 'billing_informations';

    protected $fillable = [
        'parent_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    /**
     * Get the parent user that owns this billing information.
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Get all paper requests using this billing information.
     */
    public function requestedPapers()
    {
        return $this->hasMany(RequestedPaperToHome::class, 'billing_information_id');
    }
}
