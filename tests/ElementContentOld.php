<?php


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;

/**
 * add Elemental v1 Style field to the ElementContent element
 *
 * Class ElementContentStyleExtension
 */
class ElementContentOld extends BaseElement
{
    private static $db = [
        'HTML' => 'HTMLText',
        'Style' => 'Varchar'
    ];

    public function onAfterWrite()
    {
        //copy data to ElementContentTable
        $entryExists = SQLSelect::create()
            ->setFrom('ElementContent')
            ->setWhere(['ID' => $this->ID])
            ->execute()
            ->first();
        if (!$entryExists) {
            SQLInsert::create()
                ->setInto('ElementContent')
                ->setAssignments(['ID' => $this->ID, 'Style' => $this->Style, 'HTML' => $this->HTML])
                ->execute();
        }
        if ($entryExists) {
            SQLUpdate::create()
                ->setFrom('ElementContent')
                ->setWhere(['ID' => $this->ID])
                ->setAssignments(['Style' => $this->Style, 'HTML' => $this->HTML])
                ->execute();
        }

    }
}