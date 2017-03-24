<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\Read;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\QueryScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\UnionScaffolder;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Core\ClassInfo;
use Exception;

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class Read extends QueryScaffolder implements CRUDInterface
{
    use DataObjectTypeTrait;
    use CrudTrait;

    protected $usePagination = false;

    /**
     * @return string
     */
    protected function createName()
    {
        $typeName = $this->getDataObjectInstance()->plural_name();
        $typeName = str_replace(' ', '', $typeName);
        $typeName = ucfirst($typeName);
        
        return 'read'.$typeName;
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

            $list = DataList::create($this->dataObjectClass)->first();

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

    /**
     * @return string`
     */
    public function getIdentifier()
    {
        return SchemaScaffolder::READ;
    }

    /**
     * Creates a thunk that lazily fetches the type
     * @param  Manager $manager
     * @return \Closure
     */
    protected function createTypeGetter(Manager $manager)
    {
        return function () use ($manager) {
            // Create unions for exposed descendants
            $descendants = ClassInfo::subclassesFor($this->dataObjectClass);
            array_shift($descendants);
            $union = [$this->typeName];
            foreach ($descendants as $descendant) {
                $typeName = ScaffoldingUtil::typeNameForDataObject($descendant);
                if ($manager->hasType($typeName)) {
                    $union[] = $typeName;
                }
            }
            if (sizeof($union) > 1) {
                return (new UnionScaffolder(
                    $this->typeName.'WithDescendants',
                    $union
                ))->scaffold($manager);
            }

            return $manager->getType($this->typeName);
        };
    }
}
