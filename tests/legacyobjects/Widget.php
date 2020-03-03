<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Widget extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class
    ];

    private static $db = [
        'Title' => 'Varchar(255)',
        'Sort' => 'Int',
        'Enabled' => 'Boolean',
    ];

    private static $has_one = [
        'Parent' => 'WidgetArea',
    ];
}