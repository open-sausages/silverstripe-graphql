<?php
namespace SilverStripe\GraphQL\Tests\Middleware;

use GraphQL\Executor\ExecutionResult;
use SilverStripe\GraphQL\Middleware\CacheControlMiddleware;

require_once(__DIR__ . '/MiddlewareProcessTest.php');

class CacheControlMiddlewareTest extends MiddlewareProcessTest
{
    public function testItAddsExtensionDataToArrayResult()
    {
        $result = $this->simulateMiddlewareProcess(
            new CacheControlMiddleware(),
            'query testQuery { foo }',
            [
                'extensions' => [
                    'cacheControl' => [
                        'foo' => ['maxAge' => 60]
                    ]
                ]
            ],
            [],
            function () { return []; }
        );
        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('cacheControl', $result['extensions']);
        $this->assertEquals($result['extensions']['cacheControl'], ['foo' => ['maxAge' => 60]]);
    }

    public function testItAddsExtensionDataToExecutionResultObject()
    {
        $result = $this->simulateMiddlewareProcess(
            new CacheControlMiddleware(),
            'query testQuery { foo }',
            [
                'extensions' => [
                    'cacheControl' => [
                        'foo' => ['maxAge' => 60]
                    ]
                ]
            ],
            [],
            function () { return new ExecutionResult(); }
        );
        $this->assertInstanceOf(ExecutionResult::class, $result);
        $resultArr = $result->toArray();
        $this->assertArrayHasKey('extensions', $resultArr);
        $this->assertArrayHasKey('cacheControl', $resultArr['extensions']);
        $this->assertEquals($resultArr['extensions']['cacheControl'], ['foo' => ['maxAge' => 60]]);
    }

}
