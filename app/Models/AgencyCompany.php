<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgencyCompany extends Model
{
    use SoftDeletes;

    protected $table = 'agencies_companies';

    protected $fillable = [
        'name',
        'normalized_name',
        'category',
        'contact_person',
        'phone',
        'email',
        'address',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'agency_id');
    }

    public function projectVisits(): HasMany
    {
        return $this->hasMany(ProjectVisit::class, 'agency_id');
    }

    public function companyVisits(): HasMany
    {
        return $this->hasMany(CompanyVisit::class, 'agency_id');
    }
}
