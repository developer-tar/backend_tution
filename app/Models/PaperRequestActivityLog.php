<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperRequestActivityLog extends Model
{
    protected $table = 'paper_request_activity_logs';

    protected $fillable = [
        'requested_paper_to_home_id',
        'user_id',
        'paper_id',
        'billing_information_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'requested_paper_to_home_id' => 'integer',
        'user_id' => 'integer',
        'paper_id' => 'integer',
        'billing_information_id' => 'integer',
    ];

    /**
     * Get the requested paper record.
     */
    public function requestedPaper()
    {
        return $this->belongsTo(RequestedPaperToHome::class, 'requested_paper_to_home_id');
    }

    /**
     * Get the user (parent) who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the paper.
     */
    public function paper()
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    /**
     * Get the billing information.
     */
    public function billingInformation()
    {
        return $this->belongsTo(BillingInformation::class, 'billing_information_id');
    }
}
