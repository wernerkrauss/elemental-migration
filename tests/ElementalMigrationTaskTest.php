<?php

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement as SS4BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use Netwerkstatt\ElementalMigration\Task\ElementalMigration;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;

class ElementalMigrationTaskTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    protected static $extra_dataobjects = [
        WidgetArea::class,
        ElementalAreaOld::class,
        Widget::class,
        BaseElement::class,
        PageWithoutElementalArea::class,
        ElementContentOld::class,
        ElementVirtualLinked::class,
        OldTableObject::class,
        NewTableObject::class,
    ];

    protected static $required_extensions = [
        Page::class => [OldElementalPageExtension::class],
        ElementContent::class => [ElementContentStyleExtension::class]
    ];

    protected function setUp()
    {
        parent::setUp();
        DatabaseAdmin::config()->update(
            'classname_value_remapping',
            ['ElementContentOld' => 'DNADesign\Elemental\Models\ElementContent']
        );

        ElementalMigration::config()->update(
            'data_migration',
            ['OldTableObject' => 'NewTableObject']
        );
    }


    /**
     * @useDatabase false
     */
    public function testExtensionsAreAppliedToTestedClasses(): void
    {
        foreach ([Page::class] as $className) {
            $instance = $className::create();
            $hasOldExtension = $instance->hasExtension(OldElementalPageExtension::class);
            $this->assertTrue($hasOldExtension, $className . ' should have old ElementalExtension applied');
            $hasExtension = $instance->hasExtension(ElementalPageExtension::class);
            $this->assertTrue($hasExtension, $className . ' should have new ElementalExtension applied');
        }
    }

    public function testPageHasOldElementalAreaApplied(): void
    {
        $oldArea = $this->objFromFixture(ElementalAreaOld::class, 'area1');
        $this->assertInstanceOf(WidgetArea::class, $oldArea, 'Old ElementalArea should be an instance of WidgetArea');
        $page = $this->objFromFixture(Page::class, 'some-page');
        $this->assertEquals($oldArea->ID, $page->ElementAreaID,
            'Page should have old elemental Area applied as "ElementID"');
    }

    public function testWidgetAreaMigration(): void
    {
        $oldArea = $this->objFromFixture(ElementalAreaOld::class, 'area2');
        $this->assertInstanceOf(WidgetArea::class, $oldArea, 'Old ElementalArea should be an instance of WidgetArea');

        $page = $this->objFromFixture(PageWithoutElementalArea::class, 'testpage');
        $this->assertEquals($oldArea->ID, $page->ElementAreaID,
            'Page should have old elemental Area applied as "ElementAreaID"');
        $this->assertEquals(0, $page->ElementalAreaID, 'Page should not have a new ElementalArea applied');
        $page->publishRecursive();

        $this->assertTrue($page->isPublished(), 'Page should be published');

        $pageWithArea = $this->objFromFixture(Page::class, 'some-page');
        $this->assertGreaterThan(0, $pageWithArea->ElementAreaID,
            'Page with Area should have old elemental Area applied as "ElementAreaID"');
        $this->assertGreaterThan(0, $pageWithArea->ElementalAreaID,
            'Page with Area should also have new elemental Area applied as "ElementAreaID"');
        $pageWithAreaElementalID = $pageWithArea->ElementalAreaID;


        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        //reload page after the task has run
        $page = Page::get()->byID($page->ID);
        $pageWithArea = Page::get()->byID($pageWithArea->ID);

        $this->assertGreaterThan(0, $page->ElementalAreaID,
            'Page should have new elemental Area applied as "ElementalAreaID"');
        $this->assertEquals($pageWithAreaElementalID, $pageWithArea->ElementalAreaID,
            'ElementalAreaID should not change if already exsiting');

        $elementalArea = $page->ElementalArea();
        $this->assertInstanceOf(ElementalArea::class, $elementalArea,
            'New ElementalArea should be an ElementalArea instance');

        $this->assertEquals($page->ClassName, $elementalArea->OwnerClassName,
            'OwnerClassName should be set in ElementalArea');
        $this->assertTrue($elementalArea->isPublished(), 'new ElementalArea should be published');

        foreach (['', '_Live', '_Versions'] as $stage) {
            $allPagesData = SQLSelect::create()
                ->setFrom('Page' . $stage)
                ->setWhere(['ID' => $page->ID])
                ->execute();
            foreach ($allPagesData as $pageData) {
                $this->assertEquals($page->ElementalAreaID, $pageData['ElementalAreaID'],
                    'Page still has entry without correct ElementalAreaID at Stage ' . $stage);
            }
        }

    }

    public function testBaseElementMigration(): void
    {
        //get some base elements from fixtures (applied on Widget and BaseElement table)
        $pageWithArea = $this->objFromFixture(Page::class, 'some-page');
        $pageWithArea->publishRecursive();

        $this->assertGreaterThan(0, $pageWithArea->ElementAreaID,
            'Page with Area should have old elemental Area applied as "ElementAreaID"');
        $this->assertGreaterThan(0, $pageWithArea->ElementalAreaID,
            'Page with Area should also have new elemental Area applied as "ElementAreaID"');

        $this->assertEquals(0, $pageWithArea->ElementalArea()->Elements()->count(),
            'Page should not have elements by default');

        $widget = $this->objFromFixture(BaseElement::class, 'element1');
        $widgetVersionBeforeChange = $widget->Version;
        $widget->Title = 'This is a test';
        $widget->write();
        $this->assertGreaterThan(1, $widget->Version);
        $widget->publishRecursive();
        $this->assertTrue($widget->isPublished(), 'Widget should be published to test Live table');
        //reload widget to get updated version number
        $widget = $this->objFromFixture(BaseElement::class, 'element1');


        //run task
        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        //check if the elements are available through the new class (stage, live, versioned)
        $this->assertEquals(1, $pageWithArea->ElementalArea()->Elements()->count(),
            'Page should have one element after running migration');

        //check if widget has data from the fixtures
        $baseElement = $pageWithArea->ElementalArea()->Elements()->first();

        $this->assertEquals(1, $baseElement->ID, 'Element should have same ID after migration');
        $this->assertEquals('DNADesign\Elemental\Models\BaseElement', $baseElement->ClassName);
        $this->assertEquals('This is a test', $baseElement->Title);  //changed above...
        $this->assertEquals(1, $baseElement->Sort);
        $this->assertEquals(1, $baseElement->ShowTitle);
        $this->assertEquals($baseElement->Version, $widget->Version);

        //check version before change:
        $historicalElement = $baseElement->getAtVersion($widgetVersionBeforeChange);
        $this->assertEquals('Element One', $historicalElement->Title);
    }

    public function testElementMigrationWithNamespacing(): void
    {
        $widget = $this->objFromFixture(BaseElement::class, 'element1');

        //run task
        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        $element = SS4BaseElement::get()->byID($widget->ID);
        $this->assertInstanceOf(SS4BaseElement::class, $element);
        $this->assertEquals($widget->Title, $element->Title);
    }

    public function testElementListMigration(): void
    {
        $this->markTestIncomplete('To be implemented');
    }

    public function testElementVirtualLinkedMigration(): void
    {
        $virtualElement = $this->objFromFixture('ElementVirtualLinked', 'virtual1');
        $linkedElement = $this->objFromFixture('BaseElement', 'element1');

        //run task
        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        $migratedElement = ElementVirtual::get()->byID($virtualElement->ID);
        $this->assertInstanceOf(ElementVirtual::class, $migratedElement);
        $this->assertEquals($linkedElement->ID, $migratedElement->LinkedElementID, 'Migrated Virtual Element should still link to "element1"');
    }

    /**
     * @todo: check how to copy data for version and live
     */
    public function testStyleGetsCopiedIntoElementTable(): void
    {
                foreach (ElementalMigration::TABLE_SUFFIXES as $suffix) {
            SQLUpdate::create()
                ->setFrom('Widget' . $suffix)
                ->setWhere(['ID' => 2])
                ->setAssignments(['ClassName' => 'ElementContent'])
                ->execute();
        }

        $contentElement = SQLSelect::create()
            ->setFrom('ElementContent')
            ->setWhere(['ID' => 2])
            ->execute()
            ->first();
        $this->assertArrayHasKey('Style', $contentElement);
        $this->assertEquals('some-style', $contentElement['Style']);
        $baseElement = SQLSelect::create()
            ->setFrom('Element')
            ->setWhere(['ID' => 2])
            ->execute()
            ->first();
        $this->assertFalse( $baseElement, 'Element should not be migrated yet');

        //run task
        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        $baseElement = SQLSelect::create()
            ->setFrom('Element')
            ->setWhere(['ID' => 2])
            ->execute()
            ->first();
        $this->assertArrayHasKey('Style', $baseElement);
        $this->assertEquals('some-style',$baseElement['Style'], 'Element table should have Style after running the task');
    }

    /**
     * @return array
     */
    public function provideClassNames(): array
    {
        return [
            'BaseElement' => ['BaseElement', 'DNADesign\Elemental\Models\BaseElement'],
            'ElementContent' => ['ElementContent', 'DNADesign\Elemental\Models\ElementContent']
        ];
    }

    /**
     * @dataProvider provideClassNames
     * @param $from
     * @param $to
     */
    public function testClassNameRemapping($from, $to): void
    {
        $this->assertEquals($to, ElementalMigration::getNewClassName($from));
    }

    public function testCopyDataToRenamedTables(): void
    {
        $oldTableObject = OldTableObject::create([
            'Title' => 'Let me migrate',
        'Content' => 'Migration is cool!!! ;)']);
        $oldTableObject->write();
        $oldTableObject->publishRecursive();

        //reload for updating Version
        $oldTableObject = OldTableObject::get()->byID($oldTableObject->ID);

        //run task
        $migrationTask = ElementalMigration::create();
        $migrationTask->run(new HTTPRequest('GET', ''));

        $newTableObject = NewTableObject::get()->byID($oldTableObject->ID);
        $this->assertEquals('Let me migrate', $newTableObject->Title, 'Title should be copied over to NewTableObject table');
        $this->assertEquals('Migration is cool!!! ;)', $newTableObject->Content, 'Content should be copied over to NewTableObject table');
        $this->assertTrue($newTableObject->isPublished(), 'NewTableObject should be published');
        $this->assertEquals($oldTableObject->Version, $newTableObject->Version, 'Old and New object should have same version');

    }
}