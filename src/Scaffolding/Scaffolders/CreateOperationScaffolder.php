<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders;

use SilverStripe\GraphQL\Scaffolding\DataObjectTypeTrait;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Util\TypeParser;
use SilverStripe\GraphQL\Manager;
use Exception;

/**
 * A generic "create" operation for a DataObject
 */
class CreateOperationScaffolder extends MutationScaffolder
{

    use DataObjectTypeTrait;

    /**
     * CreateOperationScaffolder constructor.
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;

        parent::__construct(
            'create' . ucfirst($this->typeName()),
            $this->typeName()
        );

        // Todo: this is totally half baked
        $this->setResolver(function ($object, array $args, $context, $info) {
            if (singleton($this->dataObjectClass)->canCreate()) {
                $newObject = Injector::inst()->createWithArgs(
                    $this->dataObjectClass,
                    $args
                );
                $newObject->write();

                return $newObject;
            } else {
                throw new Exception("Cannot create {$this->dataObjectClass}");
            }
        });

    }

    /**
     * @return array
     */
    protected function createArgs()
    {
        return [
            'Input' => [
                'type' => Type::nonNull($this->generateInputType())
            ]
        ];
    }

    /**
     * @return InputObjectType
     */
    protected function generateInputType()
    {
        $fields = [];
        $instance = $this->getDataObjectInstance();

        // Setup default input args.. Placeholder!
        $db = $instance->db();
        unset($db['ID']);

        foreach ($db as $dbFieldName => $dbFieldType) {
            $result = $instance->obj($dbFieldName);
            $typeName = $result->config()->graphql_type;
            $arr = (new TypeParser($typeName))->toArray();
            $arr['name'] = $dbFieldName;
            $fields[] = $arr;
        }

        return new InputObjectType([
            'name' => $this->typeName() . 'CreateInputType',
            'fields' => $fields
        ]);

    }
}