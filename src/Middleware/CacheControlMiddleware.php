<?php

namespace SilverStripe\GraphQL\Middleware;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Schema;

class CacheControlMiddleware implements QueryMiddleware
{
    public function process(Schema $schema, $query, $context, $params, callable $next)
    {
        $result = $next($schema, $query, $context, $params);

        if (!isset($context['extensions']['cacheControl'])) {
            return $result;
        }

        // TODO Set HTTP cache header based on smallest maxAge (or unset on scope: private)

        // Collect cache control
        $cacheControl = $context['extensions']['cacheControl'];
        if ($result instanceof ExecutionResult) {
            $newResult = $this->addToResult($result, $cacheControl);
        } else {
            $newResult = $result;
            $newResult['extensions'] = array_merge(
                isset($newResult['extensions']) ? $newResult['extensions'] : [],
                ['cacheControl' => $cacheControl]
            );
        }

        return $newResult;
    }

    /**
     * @param ExecutionResult $result
     * @param array $cacheControl
     * @return ExecutionResult
     */
    protected function addToResult($result, array $cacheControl)
    {
        $arr = $result->toArray();
        return new ExecutionResult(
            isset($arr['data']) ? $arr['data'] : [],
            isset($arr['errors']) ? $arr['errors'] : [],
            array_merge(
                isset($arr['extensions']) ? $arr['extensions'] : [],
                ['cacheControl' => $cacheControl]
            )
        );
    }
}
