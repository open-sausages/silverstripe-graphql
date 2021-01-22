<?php


namespace SilverStripe\GraphQL\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class FakeReview extends DataObject implements TestOnly
{
    private static $db = [
        'Content' => 'Varchar',
    ];

    private static $has_one = [
        'Author' => Member::class,
        'Product' => FakeProduct::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
