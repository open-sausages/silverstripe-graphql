<?php

namespace SilverStripe\GraphQL\Scaffolding\Interfaces;

/**
 * Defines the methods required for a class to provide a CRUD scaffold
 */
interface CRUDInterface
{
    /**
     * @return string
     */
    public function getIdentifier();

    /**
     * @return string
     */
    protected function createName();

    /**
     * @return \Closure
     */
    protected function createResolver();

    /**
     * @return GraphQL\Type\Definition\Type
     */
    protected function createInputType();

    /**
     * @return array
     */
    protected function createArgs();
}
