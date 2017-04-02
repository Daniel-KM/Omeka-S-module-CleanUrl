<?php

namespace CleanUrlTest\Controller;

class IndexControllerItemTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'clean_url_identifier_property' => 10,
            'clean_url_identifier_prefix' => '',
            'clean_url_main_path' => '',
            'clean_url_item_set_generic' => '',
            'clean_url_media_allowed' => [],
            'clean_url_item_allowed' => ['generic'],
            'clean_url_media_generic' => '',
            'clean_url_item_generic' => 'document/',
        ];
    }

    public function testRouteItemAction()
    {
        $site_slug = $this->site->slug();
        $item_identifier = $this->item->value('dcterms:identifier');
        $path = "/s/$site_slug/document/$item_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Index');
        $this->assertActionName('route-item');

        $this->assertQueryContentContains('#content > h2', 'Item 1');
    }
}
