<?php


use SilverStripe\Dev\TestOnly;

class BaseElement extends Widget
{
    private static $db = [
        'ExtraClass' => 'Varchar(255)',
        'HideTitle' => 'Boolean',
        'AvailableGlobally' => 'Boolean(1)'
    ];

}