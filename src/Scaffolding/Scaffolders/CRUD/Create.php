<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use SilverStripe\GraphQL\Manager;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Util\TypeParser;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use Exception;
use SilverStripe\ORM\DataObjectSchema;

/**
 * A generic "create" operation for a DataObject.
 */
class Create extends MutationScaffolder implements CRUDInterface
{
    use CrudTrait;

    const IDENTIFIER = 'create';

    /**
     * Creates a name for the input type
     * @return string
     */
    protected function inputTypeName()
    {
        return $this->typeName().'CreateInputType';
    }

    /**
     * Creates a resolver function for both item and list scope
     * @return \Closure
     */
    protected function createItemResolver()
    {
        return function ($object, array $args, $context, $info) {
            if (singleton($this->dataObjectClass)->canCreate($context['currentUser'])) {
                return $this->createRecord($args['Input']);
            }

            throw new Exception("Cannot create {$this->dataObjectClass}");
        };
    }

    /**
     * Creates a resolver function for both item and list scope
     * @return \Closure
     */
    protected function createListResolver()
    {
        return function ($object, array $args, $context, $info) {
            if (singleton($this->dataObjectClass)->canCreate($context['currentUser'])) {
                $created = [];
                foreach ($args['Input'] as $attributes) {
                    $created[] = $this->createRecord($attributes);
                }

                return $created;
            }

            throw new Exception("Cannot create {$this->dataObjectClass}");
        };
    }

    /**
     * Helper method to create a record for the object class
     * @param  array  $attributes
     * @return SilverStripe\ORM\DataObject
     */
    protected function createRecord(array $attributes)
    {
        $newObject = Injector::inst()->create($this->dataObjectClass);
        $newObject->update($attributes);
        $newObject->write();
        
        return $newObject;
    }

    /**
     * @param  $manager Manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $baseFn = function () use ($manager) {
            return $manager->getType($this->inputTypeName());
        };

        $listFn = function () use ($baseFn) {
            return Type::nonNull(Type::listOf($baseFn()));
        };

        $itemFn = function () use ($baseFn) {
            return Type::nonNull($baseFn());
        };

        return [
            'Input' => [
                'type' => $this->isListScope() ? $listFn : $itemFn
            ],
        ];
    }

    /**
     * @param  Manager $manager
     * @return InputObjectType
     */
    protected function createInputType(Manager $manager)
    {
        $fields = [];
        $instance = $this->getDataObjectInstance();

        // Setup default input args.. Placeholder!
        $schema = Injector::inst()->get(DataObjectSchema::class);
        $db = $schema->fieldSpecs($this->dataObjectClass);

        unset($db['ID']);

        foreach ($db as $dbFieldName => $dbFieldType) {
            $result = $instance->obj($dbFieldName);
            $typeName = $result->config()->graphql_type;
            $arr = [
                'type' => (new TypeParser($typeName))->getType()
            ];
            $fields[$dbFieldName] = $arr;
        }

        return new InputObjectType([
            'name' => $this->inputTypeName(),
            'fields' => $fields,
        ]);
    }
}
