<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TestRelatedBeta
 *
 * @property integer $id
 * @property string  $name
 */
class TestRelatedBeta extends Model
{
    protected $fillable = [
        'name',
    ];
    
    public function testModels()
    {
        return $this->belongsToMany(TestModel::class);
    }

    public function testRelatedAlphas()
    {
        return $this->belongsToMany(TestRelatedAlpha::class)
            ->withPivot([
                'position',
                'date',
            ]);
    }
}
