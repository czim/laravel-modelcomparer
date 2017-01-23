<?php
namespace Czim\ModelComparer\Traits;

trait ToArrayJsonable
{

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        if ( ! method_exists($this, 'toArray')) {
            throw new \RuntimeException("Can only use ToArrayJsonable on Arrayable object");
        }

        return json_encode($this->toArray(), $options);
    }

}
