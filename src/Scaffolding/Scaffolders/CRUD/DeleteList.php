<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\DataList;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;
use SilverStripe\ORM\DB;

/**
 * A generic delete operation.
 */
class DeleteList extends Delete
{
    use DataObjectTypeTrait;

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::DELETE_LIST;
    }

    /**
     * @return string
     */
    protected function createName()
    {
		return 'delete'.ucfirst($this->typeName()).'List';    	
    }

    /**
     * @return \Closure
     */
    protected function createResolver()
    {
    	return function ($object, array $args, $context, $info) {
            DB::get_conn()->withTransaction(function () use ($args, $context) {
                $results = DataList::create($this->dataObjectClass)
                    ->byIDs($args['IDs']);

                foreach ($results as $obj) {
                    if ($obj->canDelete($context['currentUser'])) {
                        $obj->delete();
                    } else {
                        throw new Exception(sprintf(
                            'Cannot delete %s with ID %s',
                            $this->dataObjectClass,
                            $obj->ID
                        ));
                    }
                }
            });
        }
    }

    /**
     * @return array
     */
    protected function createArgs()
    {
        return [
            'IDs' => [
                'type' => Type::nonNull(Type::listOf(Type::id())),
            ],
        ];
    }
}
