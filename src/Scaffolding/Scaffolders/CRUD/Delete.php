<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\DataList;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use Exception;
use SilverStripe\ORM\DB;

/**
 * A generic delete operation.
 */
class Delete extends MutationScaffolder implements CRUDInterface
{
    use CrudTrait;

    const IDENTIFIER = 'delete';

    /**
     * @return \Closure
     */
    protected function createItemResolver()
    {
        return function ($object, array $args, $context, $info) {
            DB::get_conn()->withTransaction(function () use ($args, $context) {
                $id = (int) $args['ID'];
                $result = DataList::create($this->dataObjectClass)
                    ->byID($id);
                
                if (!$result) {
                    throw new Exception(sprintf(
                        '%s #%s not found',
                        $this->dataObjectClass,
                        $id
                    ));
                }

                if (!$result->canDelete($context['currentUser'])) {
                    throw new Exception(sprintf(
                        'Cannot delete %s with ID %s',
                        $this->dataObjectClass,
                        $result->ID
                    ));
                }

                $result->delete();

                return $id;
            });
        };
    }

    /**
     * @return \Closure
     */
    protected function createListResolver()
    {
        return function ($object, array $args, $context, $info) {
            DB::get_conn()->withTransaction(function () use ($args, $context) {
                $ids = $args['IDs'];
                $deletedIDs = [];
                $results = DataList::create($this->dataObjectClass)
                    ->byIDs($ids);
                foreach ($results as $obj) {
                    if ($obj->canDelete($context['currentUser'])) {
                        $obj->delete();
                        $deletedIDs[] = $obj->ID;
                    } else {
                        throw new Exception(sprintf(
                            'Cannot delete %s with ID %s',
                            $this->dataObjectClass,
                            $obj->ID
                        ));
                    }
                }

                return $deletedIDs;
            });
        };
    }

    /**
     * There is no input type for this operation. Method is defined
     * to comply with the abstract member of CrudTrait
     * @param  Manager $manager
     * @return null
     */
    protected function createInputType(Manager $manager)
    {
        return null;
    }

    /**
     * @param  $manager Manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $key = $this->isItemScope() ? 'ID' : 'IDs';
        $type = Type::id();
        
        if ($this->isListScope()) {
            $type = Type::listOf($type);
        }

        return [
            $key => [
                'type' => Type::nonNull($type),
            ],
        ];
    }

    /**
     * Creates a thunk that lazily fetches the type
     * @param  Manager $manager
     * @return \Closure
     */
    protected function createTypeGetter(Manager $manager)
    {
        return $this->isItemScope() ? Type::id() : Type::listOf(Type::id());
    }
}
