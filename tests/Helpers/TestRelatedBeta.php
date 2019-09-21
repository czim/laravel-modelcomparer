<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property integer $id
 * @property string  $name
 */
class TestRelatedBeta extends Model
{
    protected $fillable = [
        'name',
    ];

    public function testModels(): BelongsToMany
    {
        return $this->belongsToMany(TestModel::class);
    }

    public function testRelatedAlphas(): BelongsToMany
    {
        return $this->belongsToMany(TestRelatedAlpha::class)
            ->withPivot([
                'position',
                'date',
            ]);
    }
}
