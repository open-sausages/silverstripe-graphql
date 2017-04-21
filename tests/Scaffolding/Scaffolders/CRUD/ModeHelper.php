<?php

namespace SilverStripe\GraphQL\Tests\Scaffolding\Scaffolders\CRUD;

use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;

trait ModeHelper
{
    protected function eachMode($callback)
    {
        foreach ([SchemaScaffolder::MODE_LIST_ONLY, SchemaScaffolder::MODE_ITEM_ONLY] as $mode) {
            $isList = $mode === SchemaScaffolder::MODE_LIST_ONLY;
            $callback($mode, $isList);
        }
    }
}
