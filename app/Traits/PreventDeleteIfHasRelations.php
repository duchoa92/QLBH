<?php

namespace App\Traits;

trait PreventDeleteIfHasRelations
{
    public function hasRelations(array $relations)
    {
        foreach ($relations as $relation) {
            if ($this->$relation()->exists()) {
                return true;
            }
        }

        return false;
    }
}