<?php

namespace CleanUrlTest\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;

abstract class CleanUrlControllerTestCase extends AbstractHttpControllerTestCase
{
    protected $item;
    protected $item_set;
    protected $site;
    protected $media_url;

    protected function getSettings()
    {
        return [];
    }

    public function setUp()
    {
        $this->loginAsAdmin();

        $serviceLocator = $this->getApplication()->getServiceManager();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $response = $api->create('item_sets', [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => '10',
                    '@value' => 'is1',
                ]
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => '1',
                    '@value' => 'Item Set 1',
                ]
            ],
        ]);
        $this->item_set = $response->getContent();

        $this->media_url = 'https://pixabay.com/static/uploads/photo/2015/11/19/21/14/phone-1052023__340.jpg';
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
        foreach ($cleanurl_settings as $name => $value) {
            $settings->set($name, $value);
        }

        $this->resetApplication();
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
        $this->api()->delete('item_sets', $this->item_set->id());
        $this->api()->delete('sites', $this->site->id());
    }

    protected function loginAsAdmin()
    {
        $application = $this->getApplication();
        $serviceLocator = $application->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function api()
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        return $serviceLocator->get('Omeka\ApiManager');
    }

    protected function resetApplication()
    {
        $this->application = null;
    }
}
