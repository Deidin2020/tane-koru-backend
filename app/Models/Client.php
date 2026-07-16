<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'agency_id',
        'client_name',
        'phone',
        'nationality',
        'lead_source',
        'direct_source',
        'referral_name',
        'budget',
        'currency',
        'required_unit',
        'payment_method',
        'purchase_purpose',
        'visit_date',
        'assigned_salesperson_id',
        'presentation_completed',
        'objection',
        'offer_details',
        'notes',
        'status',
        'not_interested_reason',
        'follow_up_date',
        'last_activity_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'visit_date' => 'date',
            'presentation_completed' => 'boolean',
            'follow_up_date' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(AgencyCompany::class, 'agency_id');
    }

    public function assignedSalesperson(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'assigned_salesperson_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientActivity::class);
    }
}
