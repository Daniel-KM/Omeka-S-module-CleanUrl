<?php
namespace CleanUrlTest\View\Helper;

use CleanUrl\View\Helper\GetIdentifiersFromResources;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\View\Helper\Setting;

class GetIdentifiersFromResourcesTest extends OmekaControllerTestCase
{
    protected $connection;
    protected $view;

    protected $propertyId = 10;
    protected $prefix = 'document:';

    public function setUp()
    {
        parent::setup();

        $services = $this->getServiceLocator();
        $this->connection = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');

        $this->api = $api;
        $this->settings = $settings;
        $settings->set('clean_url_identifier_property', $this->propertyId);
        $settings->set('clean_url_identifier_prefix', $this->prefix);

        $setting = new Setting($settings);
        $view = $this->getMock(
            'Zend\View\Renderer\PhpRenderer',
            [
                'api',
                'setting'
            ]
        );
        $view->expects($this->any())
            ->method('api')
            ->willReturn($api);
        $view->expects($this->any())
            ->method('setting')
            ->will($this->onConsecutiveCalls(
                $this->propertyId, $this->prefix,
                $this->propertyId, $this->prefix
            ));
        $this->view = $view;

        $this->loginAsAdmin();

        $itemSet = $this->createResource('document: my_collection_1', 'item_sets');
        $item = $this->createResource('document: my_item_1', 'items');

        $this->itemSet = $itemSet;
        $this->item = $item;
    }

    protected function getHelper($prefix = null)
    {
        $helper = new GetIdentifiersFromResources($this->connection);
        $helper->setView($this->view);
        return $helper;
    }

    public function testNoIdentifier()
    {
        $helper = $this->getHelper();
        $item = $this->api->create('items', [])->getContent();

        $identifier = $helper($item);
        $this->assertTrue(is_null($identifier));

        $identifiers = $helper([$item]);
        $this->assertTrue(is_array($identifiers));
        $this->assertEmpty($identifiers);
    }

    public function testItemSetIdentifier()
    {
        $helper = $this->getHelper();
        $this->assertEquals('my_collection_1', $helper($this->itemSet));
        $this->assertEquals([$this->itemSet->id() => 'my_collection_1'], $helper([$this->itemSet]));
    }

    public function testItemIdentifier()
    {
        $helper = $this->getHelper();
        $this->assertEquals('my_item_1', $helper($this->item));
        $this->assertEquals([$this->item->id() => 'my_item_1'], $helper([$this->item]));
    }

    public function testItemIdentifiers()
    {
        $helper = $this->getHelper();

        $items = [$this->item];
        $items[] = $this->createResource('document: my_item_2', 'items');
        $items[] = $this->api->create('items', [])->getContent();
        $item = $this->createResource('document: my_item_5', 'items');
        $items[] = $this->createResource('document: my_item_6', 'items');
        $items[] = $this->createResource('other_prefix: my_item_7', 'items');

        $expected = [
            $items[0]->id() => 'my_item_1',
            $items[1]->id() => 'my_item_2',
            $items[3]->id() => 'my_item_6',
        ];

        $this->assertEquals($expected, $helper($items));
    }

    protected function createResource($identifier, $type = 'items')
    {
        $resource = $this->api->create($type, [
            'dcterms:identifier' => [
                0 => [
                    'property_id' => $this->propertyId,
                    'type' => 'literal',
                    '@value' => $identifier,
                ],
            ],
        ])->getContent();
        return $resource;
    }
}
