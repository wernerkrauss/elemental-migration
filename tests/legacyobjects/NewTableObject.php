<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class NewTableObject extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText'
    ];

    private static $extensions = [
        Versioned::class
    ];
}