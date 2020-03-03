<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class WidgetArea extends DataObject implements TestOnly
{
    private static $has_many = array(
        'Widgets' => 'Widget'
    );
}