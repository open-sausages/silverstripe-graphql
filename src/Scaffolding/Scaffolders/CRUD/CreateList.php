<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;

/**
 * Allows the creation of multiple DataObjects in a single operation.
 */
class CreateList extends Create
{
    use DataObjectTypeTrait;

    /**
     * @return string
     */
    public function getIdentifier()
    {
    	return SchemaScaffolder::CREATE_LIST;
    }

    /**
     * @return string
     */
    protected function createName()
    {
		return 'create'.ucfirst($this->typeName()).'List';
    }

    /**
     * @return \Closure
     */
    protected function createResolver()
    {
		return function ($object, array $args, $context, $info) {
            if (singleton($this->dataObjectClass)->canCreate($context['currentUser'])) {
            	foreach($args['Input'] as $attributes) {
	                $newObject = Injector::inst()->create($this->dataObjectClass);
	                $newObject->update($attributes);
	                $newObject->write();
            	}

                return $newObject;
            } else {
                throw new Exception("Cannot create {$this->dataObjectClass}");
            }
        }    
   	}

    /**
     * @return array
     */
    protected function createArgs()
    {
        return [
            'Input' => [
                'type' => Type::nonNull(Type::listOf($this->createInputType())),
            ],
        ];
    }
}
