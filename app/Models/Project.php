<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use SoftDeletes;

    public const DEFAULT_PROJECT_NAME = 'Tane Koru';

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public static function resolveDefault(): self
    {
        $existingDefault = static::query()->where('is_default', true)->first();

        if ($existingDefault) {
            return $existingDefault;
        }

        return DB::transaction(function (): self {
            $project = static::query()->firstOrCreate(
                ['name' => static::DEFAULT_PROJECT_NAME],
                ['is_default' => true]
            );

            if (! $project->is_default) {
                static::query()
                    ->whereKeyNot($project->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                $project->forceFill(['is_default' => true])->save();
            }

            return $project;
        });
    }
}
