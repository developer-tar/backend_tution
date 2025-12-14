<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestedPaperToHome extends Model
{
    use SoftDeletes;

    protected $table = 'requested_papers_to_home';

    protected $fillable = [
        'parent_id',
        'paper_id',
        'billing_information_id',
        'requested',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'paper_id' => 'integer',
        'billing_information_id' => 'integer',
        'requested' => 'boolean',
    ];

    /**
     * Get the parent user that made this request.
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Get the paper that was requested.
     */
    public function paper()
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    /**
     * Get the billing information used for this request.
     */
    public function billingInformation()
    {
        return $this->belongsTo(BillingInformation::class, 'billing_information_id');
    }
}
