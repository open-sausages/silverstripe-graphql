<?php

namespace SilverStripe\GraphQL\Tests\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Manager;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Tests\Fake\DataObjectFake;
use SilverStripe\GraphQL\Tests\Fake\RestrictedDataObjectFake;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Security\Member;
use SilverStripe\Core\Config\Config;
use Exception;

class UpdateTest extends SapphireTest
{
    use ModeHelper;

    protected static $extra_dataobjects = [
        'SilverStripe\GraphQL\Tests\Fake\DataObjectFake',
        'SilverStripe\GraphQL\Tests\Fake\RestrictedDataObjectFake',
    ];

    public function testUpdateOperationResolver()
    {
        $update = new Update(DataObjectFake::class);
        $this->eachMode(function ($mode, $isList) use ($update) {
            $manager = new Manager();
            $update->setMode($mode);
            $update->addToManager($manager);
            $scaffold = $update->scaffold($manager);

            $record = DataObjectFake::create([
                'MyField' => 'old',
            ]);
            $ID = $record->write();

            if ($isList) {
                $args = [
                    ['ID' => $ID, 'Update' => ['MyField' => 'new']]
                ];
            } else {
                $args = ['ID' => $ID, 'Input' => ['MyField' => 'new']];
            }
            $scaffold['resolve'](
                $record,
                $args,
                [
                    'currentUser' => Member::create(),
                ],
                new ResolveInfo([])
            );
            $updatedRecord = DataObjectFake::get()->byID($ID);
            $this->assertEquals('new', $updatedRecord->MyField);
        });
    }

    public function testUpdateOperationInputType()
    {
        $update = new Update(DataObjectFake::class);
        $manager = new Manager();
        $this->eachMode(function ($mode, $isList) use ($update, $manager) {
            $update->setMode($mode);
            $update->addToManager($manager);
            $scaffold = $update->scaffold($manager);

            $reflect = new \ReflectionClass(Update::class);
            $method = $reflect->getMethod('inputTypeName');
            $method->setAccessible(true);
            $inputTypeName = $method->invoke($update);

            if ($isList) {
                $this->assertArrayHasKey('Input', $scaffold['args']);
                $type = $scaffold['args']['Input']['type']();
                $this->assertInstanceof(NonNull::class, $type);
                $unwrapped = $type->getWrappedType();
                $this->assertInstanceof(ListOfType::class, $unwrapped);
                $inputConfig = $unwrapped->getWrappedType()->config;
                $updateConfig = $inputConfig['fields']['Update']['type']()->config;
            } else {
                $this->assertArrayHasKey('ID', $scaffold['args']);
                $this->assertArrayHasKey('Input', $scaffold['args']);
                $type = $scaffold['args']['Input']['type']();
                $this->assertInstanceof(NonNull::class, $type);
                $updateConfig = $inputConfig = $type->getWrappedType()->config;
            }

            $this->assertEquals($inputTypeName, $inputConfig['name']);

            $fieldMap = [];
            foreach ($updateConfig['fields'] as $name => $fieldData) {
                $fieldMap[$name] = $fieldData['type'];
            }
            $this->assertArrayHasKey('Created', $fieldMap, 'Includes fixed_fields');
            $this->assertArrayHasKey('MyField', $fieldMap);
            $this->assertArrayHasKey('MyInt', $fieldMap);
            $this->assertArrayNotHasKey('ID', $fieldMap);
            $this->assertInstanceOf(StringType::class, $fieldMap['MyField']);
            $this->assertInstanceOf(IntType::class, $fieldMap['MyInt']);
        });
    }

    public function testUpdateOperationPermissionCheck()
    {
        $update = new Update(RestrictedDataObjectFake::class);
        $manager = new Manager();
        $this->eachMode(function ($mode, $isList) use ($update, $manager) {
            $update->setMode($mode);
            $restrictedDataobject = RestrictedDataObjectFake::create();
            $ID = $restrictedDataobject->write();
            $update->addToManager($manager);
            $scaffold = $update->scaffold($manager);

            $this->setExpectedExceptionRegExp(
                Exception::class,
                '/Cannot edit/'
            );

            $args = $isList ?
                [ ['ID' => $ID, 'Update' => []] ] :
                ['ID' => $ID, 'Input' => []];

            $scaffold['resolve'](
                $restrictedDataobject,
                $args,
                ['currentUser' => Member::create()],
                new ResolveInfo([])
            );
        });
    }
}
