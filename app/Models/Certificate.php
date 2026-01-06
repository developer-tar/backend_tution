<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'award_id',
        'student_id',
        'certificate_number',
        'issued_date',
        'achievement_details',
        'pdf_path',
        'status',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the award this certificate belongs to
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    /**
     * Get the student who received this certificate
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Scope to get only active certificates
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Generate a unique certificate number
     */
    public static function generateCertificateNumber(): string
    {
        $prefix = 'CERT';
        $year = date('Y');
        $random = strtoupper(substr(uniqid(), -6));
        return "{$prefix}-{$year}-{$random}";
    }
}
