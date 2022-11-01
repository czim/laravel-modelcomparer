<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Test\Helpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $name
 * @property bool   $flag
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TestRelatedModel extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'flag',
    ];

    public function testModels(): HasMany
    {
        return $this->hasMany(TestModel::class);
    }
}
