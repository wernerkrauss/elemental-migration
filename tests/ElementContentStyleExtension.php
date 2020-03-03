<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataExtension;

class ElementContentStyleExtension extends DataExtension implements TestOnly
{
    private static $db = [
        'Style' => 'Varchar'
    ];

}