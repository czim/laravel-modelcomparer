<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property integer $id
 * @property string  $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TestRelatedAlpha extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'flag' => 'boolean',
    ];

    public function testModel(): BelongsToMany
    {
        return $this->belongsToMany(TestModel::class);
    }

    public function testRelatedBetas(): BelongsToMany
    {
        return $this->belongsToMany(TestRelatedBeta::class)
            ->withPivot([
                'position',
                'date',
            ]);
    }
}
