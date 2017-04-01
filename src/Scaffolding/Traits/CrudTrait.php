<?php

namespace SilverStripe\GraphQL\Scaffolding\Traits;

use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;

trait CrudTrait
{
    use DataObjectTypeTrait;

    protected $scope;

    protected $mode;

    public static function getIdentifier()
    {
        return static::IDENTIFIER;
    }
    /**
     * The constructor for all CRUD operations.
     * @param string $dataObjectClass The FQCN of the DataObject
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;

        parent::__construct(
            $this->createName(),
            $this->typeName()
        );
    }

    /**
     * Sets the scaffolding mode: item only, list only, or both
     * @param string $mode
     *
     * @return  $this
     */
    public function setMode($mode)
    {
        $validModes = [
            SchemaScaffolder::ALL,
            SchemaScaffolder::MODE_ITEM_ONLY,
            SchemaScaffolder::MODE_LIST_ONLY
        ];

        if (!in_array($mode, $validModes)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid mode %s. Should be one of %s',
                $mode,
                implode('|', $validModes)
            ));
        }

        $this->mode = $mode;

        return $this;
    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        $modeToScope = [
            SchemaScaffolder::MODE_ITEM_ONLY => SchemaScaffolder::SCOPE_ITEM,
            SchemaScaffolder::MODE_LIST_ONLY => SchemaScaffolder::SCOPE_LIST,
        ];

        foreach ($modeToScope as $mode => $scope) {
            if ($this->mode === $mode || $this->mode === SchemaScaffolder::ALL) {
                $this->transformScope($scope);
                $type = $this->createInputType($manager);
                if ($type && !$manager->hasType((string) $type)) {
                    $manager->addType($type);
                }

                parent::addToManager($manager);
            }
        }
    }

    /**
     * Generates the name for the operation
     * @return string
     */
    protected function createName()
    {
        $name = static::getIdentifier() . ucfirst($this->typeName());
        
        if ($this->isListScope()) {
            $name .= 'List';
        }

        return $name;
    }

    /**
     * Creates the input type. Every CRUD operation does this differently
     * @param  Manager $manager
     * @return Type
     */
    abstract protected function createInputType(Manager $manager);

    abstract protected function createListResolver();

    abstract protected function createItemResolver();
    /**
     * Creates a resolver for the operation
     * @return \Closure
     */
    protected function createResolver()
    {
        return $this->isListScope() ?
            $this->createListResolver() :
            $this->createItemResolver();
    }

    /**
     * Switches the scope from list to item, or vice-versa
     * @param  string $scope
     */
    protected function transformScope($scope)
    {
        $validScopes = [
            SchemaScaffolder::SCOPE_ITEM,
            SchemaScaffolder::SCOPE_LIST
        ];

        if (!in_array($scope, $validScopes)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid scope %s. Should be one of %s',
                $scope,
                implode('|', $validScopes)
            ));
        }

        $this->scope = $scope;
        $this->operationName = $this->createName();
        $this->setResolver($this->createResolver());
    }

    /**
     * Returns true if the scope is item
     * @return boolean
     */
    protected function isItemScope()
    {
        return $this->scope === SchemaScaffolder::SCOPE_ITEM;
    }

    /**
     * Returns true if the scope is list
     * @return boolean
     */
    protected function isListScope()
    {
        return $this->scope === SchemaScaffolder::SCOPE_LIST;
    }

    /**
     * Creates a thunk that lazily fetches the type
     * @param  Manager $manager
     * @return \Closure
     */
    protected function createTypeGetter(Manager $manager)
    {
        $itemFn = function () use ($manager) {
            return $manager->getType($this->typeName);
        };

        if ($this->isItemScope()) {
            return $itemFn;
        }

        return function () use ($itemFn) {
            return Type::listOf($itemFn());
        };
    }
}
