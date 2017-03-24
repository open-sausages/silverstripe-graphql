<?php

namespace SilverStripe\GraphQL\Scaffolding\Traits;

trait CrudTrait 
{
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

        $this->setResolver($this->createResolver());
    }

}