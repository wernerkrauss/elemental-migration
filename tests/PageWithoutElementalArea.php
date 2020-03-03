<?php


use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Dev\TestOnly;

class PageWithoutElementalArea extends Page implements TestOnly
{
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        //remove auto generated ElementalAreaID for testing purposes
        if ($this->ElementalAreaID) {
            $elementalArea = ElementalArea::get()->byID($this->ElementalAreaID);
            $elementalArea->delete();

            $this->ElementalAreaID = 0;
        }

    }

}