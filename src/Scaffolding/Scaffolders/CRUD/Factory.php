<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use Doctrine\Instantiator\Exception\InvalidArgumentException;

class Factory
{
   

    public static function create($operationID, $dataObjectClass)
    {
        $crud = [
            Create::class,
            Read::class,
            Update::class,
            Delete::class
        ];

        foreach ($crud as $class) {
            if ($class::getIdentifier() === $operationID) {
                return new $class($dataObjectClass);
            }
        }

        $ids = array_map(function ($class) {
            return $class::getIdentifier();
        }, $crud);

        throw new InvalidArgumentException(sprintf(
            'Invalid CRUD operation %s. Must be one of %s',
            $operationID,
            implode('|', $ids)
        ));
    }
}
