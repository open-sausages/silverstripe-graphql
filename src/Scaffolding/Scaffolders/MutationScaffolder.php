<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders;

use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ManagerMutatorInterface;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffolderInterface;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;

/**
 * Scaffolds a GraphQL mutation field.
 */
class MutationScaffolder extends OperationScaffolder implements ManagerMutatorInterface, ScaffolderInterface
{
    use DataObjectTypeTrait;

    /**
     * MutationScaffolder constructor.
     *
     * @param string $operationName
     * @param string $typeName
     * @param OperationResolver|callable|null $resolver
     * @param string $class
     */
    public function __construct($operationName = null, $typeName = null, $resolver = null, $class = null)
    {
        if ($class) {
            $this->setDataObjectClass($class);
        }
        parent::__construct($operationName, $typeName, $resolver);

        if (!$this->getTypeName()) {
            $typeName = StaticSchema::inst()->typeNameForDataObject($this->getDataObjectClass());
            $this->setTypeName($typeName);
        }

    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        $this->extend('onBeforeAddToManager', $this, $manager);
        $manager->addMutation(function () use ($manager) {
            return $this->scaffold($manager);
        }, $this->getName());
    }


    /**
     * @param Manager $manager
     *
     * @return array
     */
    public function scaffold(Manager $manager)
    {
        return [
            'name' => $this->getName($manager),
            'args' => $this->createArgs($manager),
            'type' => $this->getType($manager),
            'resolve' => $this->createResolverFunction(),
        ];
    }

    /**
     * Get the type from Manager
     *
     * @param Manager $manager
     * @return Type
     */
    protected function getType(Manager $manager)
    {
        // If an explicit type name has been provided, use it.
        $typeName = $this->getTypeName();
        if ($typeName && $manager->hasType($typeName)) {
            return $manager->getType($typeName);
        }

        // Fall back on a computed type name
        $dataObjectClass = $this->getDataObjectClass();
        if ($dataObjectClass) {
            return StaticSchema::inst()->fetchFromManager(
                $this->getDataObjectClass(),
                $manager,
                StaticSchema::PREFER_SINGLE
            );
        }

        throw new InvalidArgumentException(sprintf(
            '%s must have either a typeName or dataObjectClass member defined.',
            __CLASS__
        ));
    }

    // Hack -- public version of method that shouldn't be protected see:
    //https://github.com/silverstripe/silverstripe-graphql/pull/176
    public function getResolvedType(Manager $manager)
    {
        return $this->getType($manager);
    }

    public function getResolvedTypeName(Manager $manager)
    {
        return $this->getResolvedType($manager)->config['name'];
    }
}
