<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Util\TypeParser;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInputInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;
use SilverStripe\ORM\DataObjectSchema;

/**
 * A generic "create" operation for a DataObject.
 */
class Create extends MutationScaffolder implements CRUDInputInterface
{
    use DataObjectTypeTrait;
    use CrudTrait;

    /**
     * @return string`
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::CREATE;
    }

    protected function createName()
    {
		return 'create'.ucfirst($this->typeName());
    }

    protected function createResolver()
    {
		return function ($object, array $args, $context, $info) {
            if (singleton($this->dataObjectClass)->canCreate($context['currentUser'])) {
                $newObject = Injector::inst()->create($this->dataObjectClass);
                $newObject->update($args['Input']);
                $newObject->write();

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
                'type' => Type::nonNull($this->createInputType()),
            ],
        ];
    }

    /**
     * @return InputObjectType
     */
    protected function createInputType()
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
            'name' => $this->typeName().'CreateInputType',
            'fields' => $fields,
        ]);
    }
}
