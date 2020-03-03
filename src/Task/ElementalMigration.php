<?php


namespace Netwerkstatt\ElementalMigration\Task;


use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Page;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;

class ElementalMigration extends BuildTask
{
    const SUFFIX_STAGE = '';
    const SUFFIX_LIVE = '_Live';
    const SUFFIX_VERSIONS = '_Versions';
    const TABLE_SUFFIXES = [self::SUFFIX_STAGE, self::SUFFIX_LIVE, self::SUFFIX_VERSIONS];

    protected $title = 'Elemental 1.x (SS3) to >=2.x (SS4)';

    protected $description = 'Convert Elemental Elements to new SS4 model where BaseElement and also ElementalArea extends both DO instead of Widget or WidgetArea as in 1.x.';

    /**
     * @inheritDoc
     */
    public function run($request)
    {
//        $this->cleanUpDB();
        $this->migrateTables();
        $this->migrateWidgetAreasToElementalAreas();
        $this->migrateWidgetsToBaseElements();
        $this->migrateStyleFieldFromElementsToBaseElement();
    }

    public function migrateWidgetAreasToElementalAreas()
    {
        //get pages with old ElementAreaID
        $pagesWithOldAreas = SQLSelect::create()
            ->setFrom('"Page"')
            ->execute();

        //loop over and migrate widgetAreas
        foreach ($pagesWithOldAreas as $pageData) {
            $this->migrateWidgetArea($pageData);
        }
    }

    private function migrateWidgetArea($pageData)
    {
        $pageID = $pageData['ID'];
        $areaID = $pageData['ElementAreaID'];
        $newElementalAreaID = $pageData['ElementalAreaID'];

        if (!$newElementalAreaID) {
            $page = Page::get()->byID($pageID);
            if (!$page) //Outdated PageData?
            {
                return
                    $area = SQLSelect::create()
                        ->setFrom('"WidgetArea"')
                        ->setWhere(['"WidgetArea"."ID"' => $areaID])
                        ->execute()
                        ->first();
            }

            $elementalArea = ElementalArea::create(
                [
                    'OwnerClassName' => $page->ClassName
                ]
            );

            $newElementalAreaID = $elementalArea->write();
            $elementalArea->publishRecursive();
        }

        foreach (self::TABLE_SUFFIXES as $suffix) {
            SQLUpdate::create()
                ->setFrom('Page' . $suffix)
                ->setWhere(['ID' => $pageID])
                ->setAssignments(['ElementalAreaID' => $newElementalAreaID])
                ->execute();
        }


    }

    private function migrateWidgetsToBaseElements()
    {
        foreach (self::TABLE_SUFFIXES as $suffix) {
            $widgetTable = 'Widget' . $suffix;
            $baseElementTable = 'BaseElement' . $suffix;
            $pageTable = 'Page' . $suffix;
            $elementTable = 'Element' . $suffix;

            $baseElementPredicate = '"' . $baseElementTable . '"."ID" = "' . $widgetTable . '"."ID" AND "' .
                $widgetTable . '"."ID" IS NOT NULL';
            if ($suffix === self::SUFFIX_VERSIONS) {
                $baseElementPredicate .= ' AND "' . $baseElementTable . '"."RecordID" = "' . $widgetTable . '"."RecordID"';
            }
            $pageJoinPredicate = '"' . $pageTable . '"."ElementAreaID" = "' . $widgetTable . '"."ParentID" AND "' .
                $widgetTable . '"."ParentID" IS NOT NULL AND "' .
                $widgetTable . '"."ParentID" <> 0';

            $baseElementData = SQLSelect::create()
                ->setFrom($widgetTable)
                ->setDistinct(true)
                ->addInnerJoin($baseElementTable, $baseElementPredicate)
                ->addInnerJoin($pageTable, $pageJoinPredicate)
                ->setSelect([
                    $widgetTable . '.*',
                    $baseElementTable . '.*',
                    $pageTable . '.ElementalAreaID'
                ])
                ->execute();

            foreach ($baseElementData as $data) {
                if ($suffix === self::SUFFIX_VERSIONS) {
                    unset($data['ID']);
                }

                $data['ParentID'] = $data['ElementalAreaID']; //move to new Elemental Area
                $data['ClassName'] = self::getNewClassName($data['ClassName']);
                $data['ShowTitle'] = $data['HideTitle'] ? 0 : 1;

                unset($data['ElementalAreaID']);
                unset($data['Enabled']);
                unset($data['AvailableGlobally']);
                unset($data['HideTitle']);
                unset($data['ListID']); //?

                //write to ElementTable
                SQLInsert::create()
                    ->setInto($elementTable)
                    ->setAssignments($data)
                    ->execute();
            }
        }
    }

    public static function getNewClassName($oldClassName)
    {
        $classes = DatabaseAdmin::config()->get('classname_value_remapping');

        return array_key_exists($oldClassName, $classes)
            ? $classes[$oldClassName]
            : $oldClassName;
    }

    /**
     * ElementContent has a "Style" value in SS3; in SS4 this is part of the Element Table
     * the migration task should loop over all Element Tables and if it finds a Style element it should update the Element Table
     * if the value is not set there
     */
    public function migrateStyleFieldFromElementsToBaseElement()
    {
        $elementClasses = $this->getSS4BaseElementSubclasses();
        $schema = DataObject::create()->getSchema();
        $elementsWithStyleField = array_filter(
            array_values($elementClasses),
            function ($className) use ($schema) {
                $table = $schema->tableName($className);
                $fields = [];
                try {
                    $query = sprintf("SHOW COLUMNS from %s", $table);
                    $fields = DB::query($query)->column('Field');
                } catch (exception $exception) {
                    // in tests a table for a testonly element might not exist
                }

                return array_key_exists('Style', array_flip($fields));
            }
        );

        foreach ($elementsWithStyleField as $className) {
            foreach (self::TABLE_SUFFIXES as $suffix) {
                $tableFrom = $schema->tableName($className) . $suffix;

                $styleData = SQLSelect::create()
                    ->setFrom($tableFrom)
                    ->setWhere('"Style" IS NOT NULL')
                    ->execute();
                foreach ($styleData as $styleEntry) {
                    $this->doUpdateStyle($styleEntry, $suffix);
                }
            }
        }
    }

    private function getSS4BaseElementSubclasses()
    {
        return ClassInfo::subclassesFor(BaseElement::class);
    }

    private function doUpdateStyle(array $styleData, $suffix)
    {
        $where = ($suffix === self::SUFFIX_VERSIONS)
            ? '"ID" = ' . $styleData['ID'] . ' AND "RecordID" = ' . $styleData['RecordID']
            : '"ID" = ' . $styleData['ID'];

        $table = 'Element' . $suffix;
        SQLUpdate::create()
            ->setFrom($table)
            ->setAssignments(['Style' => $styleData['Style']])
            ->setWhere($where)
            ->execute();

    }

    public function migrateTables()
    {
        foreach ($this->config()->data_migration as $old => $new) {
            $this->migrateTable($old, $new);
        }
    }

    /**
     * We assume just the table name has changed and the other fields are the same
     *
     * @param $old
     * @param $new
     */
    public function migrateTable($old, $new)
    {
        foreach (self::TABLE_SUFFIXES as $suffix) {
            $old .= $suffix;
            $new .= $suffix;

            if (!(DB::get_schema()->hasTable($old) && DB::get_schema()->hasTable($new))) {
                return;
            }

            //copy data if old and new exist

            $statement = "INSERT INTO $new SELECT * FROM $old";
            DB::query($statement);
        }
    }

    /**
     * @todo really needed?
     */
    private function cleanUpDB()
    {
        SQLDelete::create()->setFrom('Element')->execute();
        SQLDelete::create()->setFrom('Element_Live')->execute();
        SQLDelete::create()->setFrom('Element_Versions')->execute();
        SQLDelete::create()->setFrom('ElementalArea')->execute();
        SQLDelete::create()->setFrom('ElementalArea_Live')->execute();
        SQLDelete::create()->setFrom('ElementalArea_Versions')->execute();
    }
}
