<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Exception;

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class ReadList extends Read
{
    use DataObjectTypeTrait;

    protected $usePagination = true;

    /**
     * @return string`
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::READ_LIST;
    }

    /**
     * @return string
     */
    protected function createName()
    {
        $name = parent::createName();

        return $name.'List';
    }

    /**
     * @return \Closure
     */
    protected function createResolver()
    {
		return function ($object, array $args, $context, $info) {
            if (!singleton($this->dataObjectClass)->canView($context['currentUser'])) {
                throw new Exception(sprintf(
                    'Cannot view %s',
                    $this->dataObjectClass
                ));
            }

            $list = DataList::create($this->dataObjectClass);

            return $list;
        }
    }

    /**
     * Placeholder.
     * @return array
     */
    protected function createArgs()
    {
    	return [];
    }
}
