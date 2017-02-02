<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TestRelatedModel
 *
 * @property integer $id
 * @property string  $name
 * @property boolean $flag
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TestRelatedModel extends Model
{
    protected $fillable = [
        'name',
        'flag',
    ];
    
    public function testModels()
    {
        return $this->hasMany(TestModel::class);
    }
}
