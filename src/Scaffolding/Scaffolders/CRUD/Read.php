<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\QueryScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\CrudTrait;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\UnionScaffolder;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Core\ClassInfo;
use GraphQL\Type\Definition\Type;
use Exception;

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class Read extends QueryScaffolder implements CRUDInterface
{
    use CrudTrait;

    const IDENTIFIER = 'read';

    /**
     * @return \Closure
     */
    protected function createListResolver()
    {
        return function ($object, array $args, $context, $info) {
            if (!singleton($this->dataObjectClass)->canView($context['currentUser'])) {
                throw new Exception(sprintf(
                    'Cannot view %s',
                    $this->dataObjectClass
                ));
            }

            return DataList::create($this->dataObjectClass);
        };
    }

    /**
     * @return \Closure
     */
    protected function createItemResolver()
    {
        $listFn = $this->createListResolver();

        return function ($object, array $args, $context, $info) use ($listFn) {
            $result = $listFn($object, $args, $context, $info);

            return $result->first();
        };
    }

    /**
     * Placeholder.
     * @param  $manager Manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        return [];
    }

    /**
     * Placeholder. Will eventually allow search inputs
     * @param  Manager $manager
     * @return null
     */
    protected function createInputType(Manager $manager)
    {
        return null;
    }

    /**
     * Creates a thunk that lazily fetches the type
     * @param  Manager $manager
     * @return \Closure
     */
    protected function createTypeGetter(Manager $manager)
    {
        return function () use ($manager) {
            $unionTypeName = $this->typeName.'WithDescendants';
            if ($manager->hasType($unionTypeName)) {
                $baseType = $manager->getType($unionTypeName);
            } else {

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
                    $baseType = (new UnionScaffolder($unionTypeName, $union))
                        ->scaffold($manager);
                    $manager->addType($baseType);
                } else {
                    $baseType = $manager->getType($this->typeName);
                }
            }

            return $baseType;
        };
    }

    /**
     * Scaffolds the read query. Turns off pagination if necessary
     * @param  Manager $manager
     */
    public function scaffold(Manager $manager)
    {
        $this->setUsePagination($this->isListScope());

        return parent::scaffold($manager);
    }
}
