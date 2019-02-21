<?php

namespace SilverStripe\GraphQL\Tests\Fake;

use SilverStripe\Dev\TestOnly;

class ExtendedDataObjectFake extends DataObjectFake implements TestOnly
{
    private static $table_name = 'GraphQL_ExtendedDataObjectFake';

    private static $db = [
        'MyExtendedField' => 'Varchar',
    ];
}
