<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use GraphQL\Type\Definition\InputObjectType;
use SilverStripe\Core\Config\Config;
use SilverStripe\GraphQL\Scaffolding\Util\TypeParser;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInputInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class Update extends MutationScaffolder implements CRUDInputInterface
{
    use DataObjectTypeTrait;
    use CrudTrait;

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::UPDATE;
    }

    /**
     * @return string
     */
    protected function createName()
    {
		return 'update'.ucfirst($this->typeName());
    }
    
    /**
     * @return \Closure
     */
    protected function createResolver()
    {
		return function ($object, array $args, $context, $info) {
            $obj = DataList::create($this->dataObjectClass)
                ->byID($args['ID']);
            if (!$obj) {
                throw new Exception(sprintf(
                    '%s with ID %s not found',
                    $this->dataObjectClass,
                    $args['ID']
                ));
            }

            if ($obj->canEdit($context['currentUser'])) {
                $obj->update($args['Input']);
                $obj->write();

                return $obj;
            } else {
                throw new Exception(sprintf(
                    'Cannot edit this %s',
                    $this->dataObjectClass
                ));
            }
        }    	
    }

    /**
     * Use a generated Input type, and require an ID.
     *
     * @return array
     */
    protected function createArgs()
    {
        return [
            'ID' => [
                'type' => Type::nonNull(Type::id())
            ],
            'Input' => [
                'type' => Type::nonNull($this->createInputType()),
            ],
        ];
    }

    /**
     * Based on the args provided, create an Input type to add to the Manager.
     *
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
            'name' => $this->typeName().'UpdateInputType',
            'fields' => $fields,
        ]);
    }
}
