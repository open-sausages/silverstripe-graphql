<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use SilverStripe\GraphQL\Scaffolding\Util\ArgsParser;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ResolverInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Traits\Chainable;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Read;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ConfigurationApplier;
use SilverStripe\ORM\ArrayList;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ArgumentScaffolder;
use GraphQL\Type\Definition\Type;
use Exception;

/**
 * Provides functionality common to both operation scaffolders. Cannot
 * be a subclass due to their distinct inheritance chains.
 */
abstract class OperationScaffolder implements ConfigurationApplier
{
    use Chainable;

    /**
     * @var string
     */
    protected $typeName;

    /**
     * @var string
     */
    protected $operationName;

    /**
     * @var \Closure|SilverStripe\GraphQL\ResolverInterface
     */
    protected $resolver;

    /**
     * @var array
     */
    protected $args = [];

	/**
	 * @var string
	 */
	protected $scope;


    /**
     * OperationScaffolder constructor.
     *
     * @param string $operationName
     * @param Resolver|\Closure $resolver
     */
    public function __construct($operationName, $typeName, $resolver = null)
    {
        $this->operationName = $operationName;
        $this->typeName = $typeName;
        $this->args = ArrayList::create([]);
        $this->setScope(SchemaScaffolder::SCOPE_LIST);

        if ($resolver) {
            $this->setResolver($resolver);
        }
    }

    /**
     * Adds visible fields, and optional descriptions.
     *
     * Ex:
     * [
     *    'MyField' => 'Some description',
     *    'MyOtherField' // No description
     * ]
     *
     * @param array $fields
     */
    public function addArgs(array $argData)
    {
        $args = [];
        foreach ($argData as $argName => $typeStr) {
            $this->removeArg($argName);
            $this->args->add(new ArgumentScaffolder($argName, $typeStr));
        }

        return $this;
    }

    /**
     * @param $field
     * @param  $description
     *
     * @return mixed
     */
    public function addArg($argName, $typeStr, $description = null, $defaultValue = null)
    {
        $this->addArgs([$argName => $typeStr]);
        $this->setArgDescription($argName, $description);
        $this->setArgDefault($argName, $defaultValue);

        return $this;
    }

    /**
     * Sets descriptions of arguments
     * [
     *  'Email' => 'The email of the user'
     * ]
     * @param array $argData
     * @return  $this
     */
    public function setArgDescriptions(array $argData)
    {
        foreach ($argData as $argName => $description) {
            $arg = $this->args->find('argName', $argName);
            if (!$arg) {
                throw new InvalidArgumentException(sprintf(
                    'Tried to set description for %s, but it was not added to %s',
                    $argName,
                    $this->operationName
                ));
            }

            $arg->setDescription($description);
        }

        return $this;
    }

    /**
     * Sets a single arg description
     * @param string $argName
     * @param string $description
     */
    public function setArgDescription($argName, $description)
    {
        return $this->setArgDescriptions([$argName => $description]);
    }

    /**
     * Sets argument defaults
     * [
     *  'Featured' => true
     * ]
     * @param array $argData
     * @return  $this
     */
    public function setArgDefaults(array $argData)
    {
        foreach ($argData as $argName => $default) {
            $arg = $this->args->find('argName', $argName);
            if (!$arg) {
                throw new InvalidArgumentException(sprintf(
                    'Tried to set default for %s, but it was not added to %s',
                    $argName,
                    $this->operationName
                ));
            }

            $arg->setDefaultValue($default);
        }

        return $this;
    }

    /**
     * Sets a default for a single arg
     * @param string $argName
     * @param mixed $default
     */
    public function setArgDefault($argName, $default)
    {
        return $this->setArgDefaults([$argName => $default]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->operationName;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @param $arg
     *
     * @return $this
     */
    public function removeArg($arg)
    {
        return $this->removeArgs([$arg]);
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function removeArgs(array $args)
    {
        $this->args = $this->args->exclude('argName', $args);

        return $this;
    }

    /**
     * @param $resolver
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setResolver($resolver)
    {
        if (is_callable($resolver) || $resolver instanceof ResolverInterface) {
            $this->resolver = $resolver;
        } else {
            if (is_subclass_of($resolver, ResolverInterface::class)) {
                $this->resolver = Injector::inst()->create($resolver);
            } else {
                throw new InvalidArgumentException(sprintf(
                    '%s::setResolver() accepts closures, instances of %s or names of resolver subclasses.',
                    __CLASS__,
                    ResolverInterface::class
                ));
            }
        }

        return $this;
    }

    /**
     * Sets the scope to item or list
     * @param string $scope
     */
    public function setScope($scope)
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

        return $this;	
    }    

    /**
     * @param array $config
     *
     * @return OperationScaffolder
     */
    public function applyConfig(array $config)
    {
        if (isset($config['args'])) {
            if (!is_array($config['args'])) {
                throw new Exception(sprintf(
                    'args must be an array on %s',
                    $this->operationName
                ));
            }
            foreach ($config['args'] as $argName => $argData) {
                if (is_array($argData)) {
                    if (!isset($argData['type'])) {
                        throw new Exception(sprintf(
                            'Argument %s must have a type',
                            $argName
                        ));
                    }

                    $scaffolder = new ArgumentScaffolder($argName, $argData['type']);
                    $scaffolder->applyConfig($argData);
                    $this->removeArg($argName);
                    $this->args->add($scaffolder);
                } elseif (is_string($argData)) {
                    $this->addArg($argName, $argData);
                } else {
                    throw new Exception(sprintf(
                        'Arg %s should be mapped to a string or an array',
                        $argName
                    ));
                }
            }
        }
        if (isset($config['resolver'])) {
            $this->setResolver($config['resolver']);
        }
        if (isset($config['scope'])) {
        	$this->setScope($config['scope']);
        }

        return $this;
    }

    /**
     * Based on the type of resolver, create a function that invokes it.
     *
     * @return Closure
     */
    protected function createResolverFunction()
    {
        $resolver = $this->resolver;

        return function () use ($resolver) {
            $args = func_get_args();
            if (is_callable($resolver)) {
                return call_user_func_array($resolver, $args);
            } else {
                if ($resolver instanceof ResolverInterface) {
                    return call_user_func_array([$resolver, 'resolve'], $args);
                } else {
                    throw new \Exception(sprintf(
                        '%s resolver must be a closure or implement %s',
                        __CLASS__,
                        ResolverInterface::class
                    ));
                }
            }
        };
    }

    /**
     * Parses the args to proper graphql-php spec.
     *
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $args = [];
        foreach ($this->args as $scaffolder) {
            $args[$scaffolder->argName] = $scaffolder->toArray();
        }

        return $args;
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
     * Creates a function that returns a wrapped or unwrapped type, depending
     * on the scope
     * @param  Manager $manager 
     * @return \Closure
     */
    protected function createTypeGetter(Manager $manager)
    {
    	$baseFn = $this->createBaseTypeGetter($manager);

    	if($this->isListScope()) {
    		return function () use ($baseFn) {
    			return Type::listOf($baseFn());
    		};
    	}

    	return $baseFn;
    }

    /**
     * Creates a getter for the unwrapped type
     * @param  Manager $manager 
     * @return \Closure           
     */
    protected function createBaseTypeGetter(Manager $manager)
    {
    	return function () use ($manager) {
            return $manager->getType($this->typeName);
        };
    }

}
