<?php

namespace SilverStripe\GraphQL\Scaffolding\Interfaces;

/**
 * Defines the methods required for a class to provide a CRUD scaffold
 * that uses Input types.
 */
interface CRUDInputInterface extends CRUDInterface
{
    /**
     * @return GraphQL\Type\Definition\Type
     */
    protected function createInputType();

}
