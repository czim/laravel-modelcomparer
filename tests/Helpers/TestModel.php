<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Test\Helpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property integer $id
 * @property string  $name
 * @property int     $integer
 * @property float   $float
 * @property string  $text
 * @property bool    $boolean
 * @property Carbon  $created_at
 * @property Carbon  $updated_at
 */
class TestModel extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'integer',
        'float',
        'text',
        'boolean',
        'test_related_model_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'integer' => 'int',
        'float'   => 'float',
        'boolean' => 'bool',
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
