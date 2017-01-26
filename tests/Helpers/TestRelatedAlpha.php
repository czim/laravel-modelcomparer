<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TestRelatedAlpha
 *
 * @property integer $id
 * @property string  $name
 */
class TestRelatedAlpha extends Model
{
    protected $fillable = [
        'name',
    ];
    
    public function testModel()
    {
        return $this->belongsToMany(TestModel::class);
    }

    public function testRelatedBetas()
    {
        return $this->belongsToMany(TestRelatedBeta::class)
            ->withPivot([
                'position',
                'date',
            ]);
    }
}
