<?php


namespace SilverStripe\GraphQL\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class FakeProduct extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => FakeProductPage::class,
    ];

    private static $has_many = [
        'Reviews' => FakeReview::class,
    ];

    private static $many_many = [
        'RelatedProducts' => FakeProduct::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $owns = [
        'Reviews',
    ];
}
