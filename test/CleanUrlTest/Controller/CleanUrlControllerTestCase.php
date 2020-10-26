<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class CleanUrlControllerTestCase extends OmekaControllerTestCase
{
    protected $site;
    protected $item_set;
    protected $item_set_2;
    protected $item;
    protected $media_url;

    protected function getSettings()
    {
        return [];
    }

    public function setUp(): void
    {
        $this->loginAsAdmin();

        $serviceLocator = $this->getApplication()->getServiceManager();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $response = $api->create('item_sets', [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => '10',
                    '@value' => 'my_item_set',
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => '1',
                    '@value' => 'Item Set 1',
                ],
            ],
        ]);
        $this->item_set = $response->getContent();

        $this->item_set_2 = $api->create('item_sets', [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => '10',
                    '@value' => 'my_item_set_bis',
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => '1',
                    '@value' => 'Item Set 2',
                ],
            ],
        ])->getContent();

        $this->media_url = 'http://farm8.staticflickr.com/7495/28077970085_4d976b3c96_z_d.jpg';
        $response = $api->create('items', [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => '10',
                    '@value' => 'item1',
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => '1',
                    '@value' => 'Item 1',
                ],
            ],
            'o:item_set' => [$this->item_set->id()],
            'o:media' => [
                [
                    'o:ingester' => 'url',
                    'ingest_url' => $this->media_url,
                    'dcterms:identifier' => [
                        [
                            'type' => 'literal',
                            'property_id' => 10,
                            '@value' => 'media1',
                        ],
                    ],
                ],
            ],
        ]);
        $this->item = $response->getContent();

        $response = $api->create('sites', [
            'o:slug' => 'site1',
            'o:theme' => 'default',
            'o:title' => 'Site 1',
            'o:is_public' => 1,
        ]);
        $this->site = $response->getContent();

        $settings = $serviceLocator->get('Omeka\Settings');
        $cleanurl_settings = $this->getSettings();
        if (!isset($cleanurl_settings['cleanurl_quick_settings']['item_set_regex'])) {
            $cleanurl_settings['cleanurl_quick_settings']['item_set_regex'] = 'my_item_set|my_item_set_bis';
        }
        foreach ($cleanurl_settings as $name => $value) {
            $settings->set($name, $value);
        }

        $this->resetApplication();
    }

    public function tearDown(): void
    {
        $this->api()->delete('items', $this->item->id());
        $this->api()->delete('item_sets', $this->item_set->id());
        $this->api()->delete('item_sets', $this->item_set_2->id());
        $this->api()->delete('sites', $this->site->id());
    }
}
