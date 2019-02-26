<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders;

use Exception;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ManagerMutatorInterface;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataObject;

/**
 * Scaffolds a set of interfaces which apply to a DataObject,
 * based on its class ancestry. This allows efficient querying
 * of the union types created through {@link InheritanceScaffolder}.
 *
 * Without interfaces, using "...on <type>" in unions
 * will break queries as soon as a new type is added,
 * even if you don't need fields from this new type on your specific query.
 * The query can return entries of the new type, and without altering the query,
 * none of the already queried fields are selected on this new type.
 * Interfaces are effectively a workaround
 * to represent a dynamic inheritance model in stable API types.
 *
 * Does not apply those interfaces to the DataObject type.
 * Use {@link DataObjectScaffolder->setInterfaceTypeNames()} for this purpose.
 */
class InterfaceScaffolder implements ManagerMutatorInterface
{
    use Configurable;

    /**
     * @var string
     */
    protected $rootClass;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @param string $rootDataObjectClass
     * @param string $suffix
     */
    public function __construct($rootDataObjectClass, $suffix = '')
    {
        $this->setRootClass($rootDataObjectClass);
        $this->setSuffix($suffix);
    }

    /**
     * @return string
     */
    public function getRootClass()
    {
        return $this->rootClass;
    }

    /**
     * @param string $rootClass
     * @return self
     */
    public function setRootClass($rootClass)
    {
        if (!class_exists($rootClass)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s does not exist.',
                $rootClass
            ));
        }

        if (!is_subclass_of($rootClass, DataObject::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s is not a subclass of %s.',
                $rootClass,
                DataObject::class
            ));
        }

        $this->rootClass = $rootClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @param string $suffix
     * @return $this
     */
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTypeNames()
    {
        return array_map(function($baseTypeName) {
            return $this->generateInterfaceTypeName($baseTypeName);
        }, $this->getBaseTypeNames());
    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        foreach ($this->getBaseTypeNames() as $baseTypeName) {
            $interfaceTypeName = $this->generateInterfaceTypeName($baseTypeName);
            if (!$manager->hasType($interfaceTypeName)) {
                $manager->addType(
                    $this->scaffoldInterfaceType($manager, $baseTypeName),
                    $this->generateInterfaceTypeName($baseTypeName)
                );
            }
        }
    }

    /**
     * @param Manager $manager
     * @param string $baseTypeName
     * @return InterfaceType
     */
    public function scaffoldInterfaceType(Manager $manager, $baseTypeName)
    {
        return new InterfaceType([
            'name' => $this->generateInterfaceTypeName($baseTypeName),
            // Use same fields as base type
            'fields' => function () use ($manager, $baseTypeName) {
                return $this->createFields($manager, $baseTypeName);
            },
            'resolveType' => function ($obj) use ($manager) {
                if (!$obj instanceof DataObject) {
                    throw new Exception(sprintf(
                        'Type with class %s is not a DataObject',
                        get_class($obj)
                    ));
                }
                $class = get_class($obj);
                while ($class !== DataObject::class) {
                    $typeName = StaticSchema::inst()->typeNameForDataObject($class);
                    if ($manager->hasType($typeName)) {
                        return $manager->getType($typeName);
                    }
                    $class = get_parent_class($class);
                }
                throw new Exception(sprintf(
                    'There is no type defined for %s, and none of its ancestors are defined.',
                    get_class($obj)
                ));
            }
        ]);
    }

    protected function createFields(Manager $manager, $baseTypeName)
    {
//        $excludeFields = ['Versions'];
        $excludeFields = [];

        $baseType = $manager->getType($baseTypeName);
        return array_filter($baseType->getFields(), function ($field) use ($excludeFields) {
            return !in_array($field->name, $excludeFields);
        });
    }

    /**
     * @return array
     */
    protected function getBaseTypeNames()
    {
        $schema = StaticSchema::inst();

        $tree = array_merge(
            [$this->rootClass],
            $schema->getAncestry($this->rootClass)
        );

        return array_map(function ($class) use ($schema) {
            return $schema->typeNameForDataObject($class);
        }, $tree);
    }

    /**
     * @return string
     */
    protected function generateInterfaceTypeName($typeName)
    {
        return $typeName . $this->suffix;
    }
}
