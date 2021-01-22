<?php


namespace SilverStripe\GraphQL\Tests\Fake;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class FakeProductPage extends SiteTree implements TestOnly
{
    private static $db = [
        'BannerContent' => 'Varchar',
    ];

    private static $has_many = [
        'Products' => FakeProduct::class,
    ];

    private static $owns = [
        'Products',
    ];
}
