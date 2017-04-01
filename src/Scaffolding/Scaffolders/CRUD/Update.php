<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use GraphQL\Type\Definition\InputObjectType;
use SilverStripe\GraphQL\Scaffolding\Util\TypeParser;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use Exception;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class Update extends MutationScaffolder implements CRUDInterface
{
    use CrudTrait;

    const IDENTIFIER = 'update';

    /**
     * Creates a name for the input type
     * @return string
     */
    protected function inputTypeName()
    {
        return $this->isListScope() ?
            $this->listInputTypeName() :
            $this->itemInputTypeName();
    }

    /**
     * Creates a name for the item input type
     * @return string
     */
    protected function itemInputTypeName()
    {
        return $this->typeName().'UpdateInputType';
    }

    /**
     * Creates a name for the list input type
     * @return string
     */
    protected function listInputTypeName()
    {
        return $this->typeName().'UpdateListInputType';
    }

    /**
     * @return \Closure
     */
    protected function createItemResolver()
    {
        return function ($object, array $args, $context, $info) {
            $id = $args['ID'];
            $input = $args['Input'];
            $obj = DataList::create($this->dataObjectClass)
                ->byID($id);
            if (!$obj) {
                throw new Exception(sprintf(
                    '%s with ID %s not found',
                    $this->dataObjectClass,
                    $id
                ));
            }

            if (!$obj->canEdit($context['currentUser'])) {
                throw new Exception(sprintf(
                    'Cannot edit this %s',
                    $this->dataObjectClass
                ));
            }

            $obj->update($input);
            $obj->write();

            return $obj;
        };
    }

    /**
     * @return \Closure
     */
    protected function createListResolver()
    {
        return function ($object, array $args, $context, $info) {
            $ids = array_column($args, 'ID');
            $inputs = array_column($args, 'Update');
            $updates = [];

            foreach ($ids as $index => $id) {
                $obj = DataList::create($this->dataObjectClass)
                    ->byID($id);
                if (!$obj) {
                    throw new Exception(sprintf(
                        '%s with ID %s not found',
                        $this->dataObjectClass,
                        $id
                    ));
                }
                
                if ($obj->canEdit($context['currentUser'])) {
                    $obj->update($inputs[$index]);
                    $obj->write();

                    $updates[] = $obj;
                } else {
                    throw new Exception(sprintf(
                        'Cannot edit this %s',
                        $this->dataObjectClass
                    ));
                }
            }

            return $updates;
        };
    }

    /**
     * Use a generated Input type
     *
     * @param  $manager Manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $inputName = $this->inputTypeName();

        $baseFn = function () use ($manager, $inputName) {
            return $manager->getType($inputName);
        };

        $itemFn = function () use ($baseFn) {
            return Type::nonNull($baseFn());
        };

        $listFn = function () use ($baseFn) {
            return Type::nonNull(Type::listOf($baseFn()));
        };

        return [
            'ID' => [
                'type' => Type::nonNull(Type::id())
            ],
            'Input' => [
                'type' => $this->isListScope() ? $listFn : $itemFn
            ],
        ];
    }

    /**
     * Creates the input type
     * @param  Manager $manager
     * @return Type
     */
    protected function createInputType(Manager $manager)
    {
        return $this->isListScope() ?
            $this->createListInputType($manager) :
            $this->createItemInputType($manager);
    }

    /**
     * Creates the update input type
     * @param  Manager $manager
     * @return InnputObjectType
     */
    protected function createItemInputType(Manager $manager)
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
            'name' => $this->itemInputTypeName(),
            'fields' => $fields,
        ]);
    }

    /**
     * Creates the input type for list updates
     * @param  Manager $manager
     * @return InputObjectType
     */
    protected function createListInputType(Manager $manager)
    {
        return new InputObjectType([
            'name' => $this->listInputTypeName(),
            'fields' => [
                'ID' => [
                    'type' => Type::nonNull(Type::id())
                ],
                'Update' => [
                    'type' => function () use ($manager) {
                        return $manager->getType($this->itemInputTypeName());
                    }
                ]
            ]
        ]);
    }
}
