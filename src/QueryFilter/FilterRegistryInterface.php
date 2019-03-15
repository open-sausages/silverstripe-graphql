<?php


namespace SilverStripe\GraphQL\QueryFilter;

/**
 * Manages named {@link FieldFilterInterface} instances.
 */
interface FilterRegistryInterface
{
    /**
     * @param string $identifier
     * @return mixed
     */
    public function getFilterByIdentifier($identifier);

    /**
     * @return FieldFilterInterface[]
     */
    public function getAll();

    /**
     * @param FieldFilterInterface $filter
     * @param string|null $identifier
     * @return $this
     */
    public function addFilter(FieldFilterInterface $filter, $identifier = null);
}
