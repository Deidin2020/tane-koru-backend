<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'agency_id',
        'visit_date',
        'category',
        'sales_rep_id',
        'feedback',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(AgencyCompany::class, 'agency_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'sales_rep_id');
    }
}
