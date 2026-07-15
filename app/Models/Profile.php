<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'avatar',
        'is_salesperson',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_salesperson' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_salesperson_id');
    }

    public function projectVisits(): HasMany
    {
        return $this->hasMany(ProjectVisit::class, 'sales_rep_id');
    }

    public function companyVisits(): HasMany
    {
        return $this->hasMany(CompanyVisit::class, 'sales_rep_id');
    }
}
