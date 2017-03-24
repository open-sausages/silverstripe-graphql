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
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class UpdateList extends Update
{
    use DataObjectTypeTrait;


    /**
     * @return string
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::UPDATE_LIST;
    }

    /**
     * Use a generated Input type, and require an ID.
     *
     * @return array
     */
    protected function createArgs()
    {
        return [
        	'Input' => [
        		'type' => Type::nonNull(Type::listOf($this->createInputType()))
        	]
        ];
    }

    /**
     * @return string
     */
    protected function createName()
    {
		return 'update'.ucfirst($this->typeName()).'List';
    }

    /**
     * @return \Closure
     */
    protected function createResolver()
    {
		return function ($object, array $args, $context, $info) {
	        DB::get_conn()->withTransaction(function () use ($args, $context) {			
	            foreach($args['Input'] as $update) {
	            	$result = DataList::create($this->dataObjectClass)
	            		->byID($update['ID']);

	            	if(!$result) {
		                throw new Exception(sprintf(
		                    '%s with ID %s not found',
		                    $this->dataObjectClass,
		                    $args['ID']
		                ));            		
	            	}
		            if ($obj->canEdit($context['currentUser'])) {
		                $obj->update($args['Update']);
		                $obj->write();

		                return $obj;
		            } else {
		                throw new Exception(sprintf(
		                    'Cannot edit this %s',
		                    $this->dataObjectClass
		                ));
		            }
	            }
	        })
        }    	
    }

    /**
     * Allow oa list of [ID, Update] pairs to update many records in a single operation
     *
     * @return InputObjectType
     */
    protected function createInputType()
    {
    	$inputType = parent::createInputType();

    	return new InputObjectType([
    		'name' => $this->typeName().'UpdateListInputType',
    		'fields' => [
    			'ID' => [
    				'type' => Type::nonNull(Type::id())
    			],
    			'Update' => [
    				'type' => $inputType
    			]
    		]
    	]);
    }
}
