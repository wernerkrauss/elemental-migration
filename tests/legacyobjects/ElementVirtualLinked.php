<?php


use SilverStripe\Dev\TestOnly;

class ElementVirtualLinked extends BaseElement
{
    private static $has_one = array(
        'LinkedElement' => 'BaseElement'
    );
}