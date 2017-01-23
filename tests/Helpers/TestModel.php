<?php
namespace Czim\ModelComparer\Test\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TestModel
 *
 * @property integer $id
 * @property string  $name
 * @property integer integer
 * @property float   $float
 * @property string  $text
 * @property bool    $boolean
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
    
    public function testRelatedModel()
    {
        return $this->belongsTo(TestRelatedModel::class);
    }
}
