<?php

namespace SilverStripe\GraphQL\Tests\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Tests\Fake\DataObjectFake;
use SilverStripe\GraphQL\Tests\Fake\RestrictedDataObjectFake;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Security\Member;
use Exception;

class DeleteTest extends SapphireTest
{
    use ModeHelper;

    protected static $extra_dataobjects = [
        'SilverStripe\GraphQL\Tests\Fake\DataObjectFake',
        'SilverStripe\GraphQL\Tests\Fake\RestrictedDataObjectFake',
    ];

    public function testDeleteOperationResolver()
    {
        $delete = new Delete(DataObjectFake::class);
        $this->eachMode(function ($mode, $isList) use ($delete) {
            $delete->setMode($mode);
            $manager = new Manager();
            $delete->addToManager($manager);
            $scaffold = $delete->scaffold($manager);

            $record = DataObjectFake::create();
            $ID1 = $record->write();

            $record = DataObjectFake::create();
            $ID2 = $record->write();

            $record = DataObjectFake::create();
            $ID3 = $record->write();
            $args = $isList ?
                ['IDs' => [$ID1, $ID2]] :
                ['ID' => $ID1];

            $scaffold['resolve'](
                $record,
                $args,
                [
                    'currentUser' => Member::create(),
                ],
                new ResolveInfo([])
            );

            $this->assertNull(DataObjectFake::get()->byID($ID1));
            if ($mode === SchemaScaffolder::MODE_LIST_ONLY) {
                $this->assertNull(DataObjectFake::get()->byID($ID2));
            }
            $this->assertInstanceOf(DataObjectFake::class, DataObjectFake::get()->byID($ID3));
        });
    }

    public function testDeleteOperationArgs()
    {
        $delete = new Delete(DataObjectFake::class);

        $this->eachMode(function ($mode, $isList) use ($delete) {
            $delete->setMode($mode);
            $manager = new Manager();
            $delete->addToManager($manager);
            $scaffold = $delete->scaffold($manager);
            $key = $isList ? 'IDs' : 'ID';
            $this->assertArrayHasKey($key, $scaffold['args']);
            $this->assertInstanceof(NonNull::class, $scaffold['args'][$key]['type']);
            $unwrapped = $scaffold['args'][$key]['type']->getWrappedType();
            if ($isList) {
                $this->assertInstanceOf(ListOfType::class, $unwrapped);
                $idType = $unwrapped->getWrappedType();
                $this->assertInstanceof(IDType::class, $idType);
            } else {
                $this->assertInstanceOf(IDType::class, $unwrapped);
            }
        });
    }

    public function testDeleteOperationPermissionCheck()
    {
        $delete = new Delete(RestrictedDataObjectFake::class);
        $this->eachMode(function ($mode, $isList) use ($delete) {
            $manager = new Manager();
            $delete->setMode($mode);
            $delete->addToManager($manager);
            $restrictedDataobject = RestrictedDataObjectFake::create();
            $ID = $restrictedDataobject->write();

            $scaffold = $delete->scaffold($manager);

            $this->setExpectedExceptionRegExp(
                Exception::class,
                '/Cannot delete/'
            );
            $args = $isList ? ['IDs' => [$ID]] : ['ID' => $ID];
            $scaffold['resolve'](
                $restrictedDataobject,
                $args,
                ['currentUser' => Member::create()],
                new ResolveInfo([])
            );
        });
    }
}
