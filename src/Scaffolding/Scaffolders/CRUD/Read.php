<?php

namespace SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD;

use Exception;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Extensions\TypeCreatorExtension;
use SilverStripe\GraphQL\Scaffolding\Interfaces\CRUDInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Filters\SearchFilter;
use SilverStripe\Security\Member;
use ReflectionClass;

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class Read extends ListQueryScaffolder implements OperationResolver, CRUDInterface
{
    /**
     * @var array
     */
    private static $searchFilterTags;

    /**
     * WARNING: this is insanity. Need a way of reverse engineering the Injector aliases
     * @return array
     */
    public static function getSearchFilterTags()
    {
        if (self::$searchFilterTags) {
            return self::$searchFilterTags;
        }

        $filterClasses = ClassInfo::subclassesFor(SearchFilter::class);
        $tags = [];
        foreach ($filterClasses as $filterClass) {
            $reflection = new ReflectionClass($filterClass);
            if (!$reflection->isInstantiable()) continue;
            $tags[] = preg_replace('/Filter$/', '', $reflection->getShortName());
        }

        return self::$searchFilterTags = $tags;
    }

    /**
     * Read constructor.
     *
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        parent::__construct(null, null, $this, $dataObjectClass);
    }

    /**
     * @param array $args
     * @return DataList
     */
    protected function getResults($args)
    {
        $list = DataList::create($this->getDataObjectClass());
        if (!empty($args['Filter'])) {
            $list = $list->filter($this->normaliseKeys($args['Filter']));
        }
        if (!empty($args['Exclude'])) {
            $list = $list->exclude($this->normaliseKeys($args['Exclude']));
        }

        return $list;
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = parent::getName();
        if ($name) {
            return $name;
        }

        $typePlural = $this->pluralise($this->getTypeName());
        return 'read' . ucfirst($typePlural);
    }

    /**
     * @param Member $member
     * @return boolean
     */
    protected function checkPermission(Member $member = null)
    {
        return $this->getDataObjectInstance()->canView($member);
    }

    /**
     * @param DataObjectInterface $object
     * @param array $args
     * @param array $context
     * @param ResolveInfo $info
     * @return mixed
     * @throws Exception
     */
    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        if (!$this->checkPermission($context['currentUser'])) {
            throw new Exception(sprintf(
                'Cannot view %s',
                $this->getDataObjectClass()
            ));
        }

        $list = $this->getResults($args);
        $this->extend('updateList', $list, $args, $context, $info);
        return $list;
    }

    /**
     * Pluralise a name
     *
     * @param string $typeName
     * @return string
     */
    protected function pluralise($typeName)
    {
        // Ported from DataObject::plural_name()
        if (preg_match('/[^aeiou]y$/i', $typeName)) {
            $typeName = substr($typeName, 0, -1) . 'ie';
        }
        $typeName .= 's';
        return $typeName;
    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        $manager->addType($this->generateInputType($manager, 'Filter'));
        $manager->addType($this->generateInputType($manager, 'Exclude'));
        parent::addToManager($manager);
    }

    /**
     * Use a generated Input type, and require an ID.
     *
     * @param Manager $manager
     * @return array
     */
    protected function createDefaultArgs(Manager $manager)
    {
        return [
            'Filter' => [
                'type' => $manager->getType($this->inputTypeName('Filter')),
            ],
            'Exclude' => [
                'type' => $manager->getType($this->inputTypeName('Exclude')),
            ],
        ];
    }

    /**
     * Temporary hack until we have a proper search scaffolder
     * @todo Implement a proper search scaffolder
     * @param Manager $manager
     * @param string $key
     * @return InputObjectType
     */
    protected function generateInputType(Manager $manager, $key = '')
    {
        return new InputObjectType([
            'name' => $this->inputTypeName($key),
            'fields' => function () use ($manager) {
                $fields = [];
                $db = DataObject::getSchema()->databaseFields($this->getDataObjectClass());
                foreach ($db as $name => $spec) {
                    /* @var DBField|TypeCreatorExtension $db */
                    $db = $this->getDataObjectInstance()->dbObject($name);
                    $fields[$name] = [
                        'type' => $db->getGraphQLType($manager),
                    ];
                    foreach (self::getSearchFilterTags() as $tag) {
                        $fieldName = $name . '__' . $tag;
                        $fields[$fieldName] = [
                            'type' => $db->getGraphQLType($manager),
                        ];
                    }
                }
                return $fields;
            }
        ]);
    }

    /**
     * @param string $key
     * @return string
     */
    protected function inputTypeName($key = '')
    {
        return $this->getTypeName() . $key . 'InputType';
    }

    /**
     * @param array $filters
     * @return array
     */
    protected function normaliseKeys(array $filters)
    {
        $result = [];
        foreach ($filters as $key => $val) {
            $pos = strrpos($key, '__');
            if($pos !== false) {
                $key = substr_replace($key, ':', $pos, 2);
            }
            $result[$key] = $val;
        }

        return $result;
    }

}
