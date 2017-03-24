<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\Delete;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use SilverStripe\ORM\DataList;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;
use SilverStripe\ORM\DB;

/**
 * A generic delete operation.
 */
class Delete extends MutationScaffolder implements CRUDInterface
{
    use DataObjectTypeTrait;
    use CrudTrait;

    /**
     * @return string
     */
    protected function createName()
    {
		return 'delete'.ucfirst($this->typeName());    	
    }

    /**
     * @return \Closure
     */
    protected function createResolver()
    {
    	return function ($object, array $args, $context, $info) {
            DB::get_conn()->withTransaction(function () use ($args, $context) {
                $obj = DataList::create($this->dataObjectClass)
                    ->byID($args['ID']);
                if($obj) {
                    if ($obj->canDelete($context['currentUser'])) {
                        $obj->delete();
                    } else {
                        throw new Exception(sprintf(
                            'Cannot delete %s with ID %s',
                            $this->dataObjectClass,
                            $obj->ID
                        ));
                    }
                } else {
                    throw new Exception(sprintf(
                        '%s with ID %s not found',
                        $this->dataObjectClass,
                        $obj->ID
                    ));                	
                }    
            });
        }
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::DELETE;
    }

    /**
     * @return array
     */
    protected function createArgs()
    {
        return [
            'ID' => [
                'type' => Type::nonNull(Type::id()),
            ],
        ];
    }
}
