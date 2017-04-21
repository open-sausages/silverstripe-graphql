<?php

namespace SilverStripe\GraphQL\Scaffolding\Traits;

use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use GraphQL\Type\Definition\Type;

trait CrudTrait
{
    use DataObjectTypeTrait;

    /**
     * @var string
     */
    protected $mode;

    /**
     * A unique identifier for the CRUD operation
     * @return string
     */
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
        $this->mode = SchemaScaffolder::ALL;

        parent::__construct(
            $this->createName(),
            $this->typeName()
        );

        $this->setResolver($this->createResolver());
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
        $this->setScope($scope);
        $this->operationName = $this->createName();
        $this->setResolver($this->createResolver());
    }
}
