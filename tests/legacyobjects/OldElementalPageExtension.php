<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataExtension;

class OldElementalPageExtension extends DataExtension implements TestOnly
{
    private static $has_one = [
        'ElementArea' => ElementalAreaOld::class
    ];
}