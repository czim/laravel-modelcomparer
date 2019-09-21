<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property integer $id
 * @property string  $name
 * @property integer integer
 * @property float   $float
 * @property string  $text
 * @property bool    $boolean
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TestModel extends Model
{
    protected $fillable = [
        'name',
        'integer',
        'float',
        'text',
        'boolean',
        'test_related_model_id',
    ];

    protected $casts = [
        'integer' => 'integer',
        'float'   => 'float',
        'boolean' => 'boolean',
    ];

    public function testRelatedModel(): BelongsTo
    {
        return $this->belongsTo(TestRelatedModel::class);
    }

    public function testRelatedAlphas(): BelongsToMany
    {
        return $this->belongsToMany(TestRelatedAlpha::class);
    }

}
