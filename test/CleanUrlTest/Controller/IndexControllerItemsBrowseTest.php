<?php

namespace CleanUrlTest\Controller;

class IndexControllerItemsBrowseTest extends CleanUrlControllerTestCase
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

    public function testRouteItemsBrowseAction()
    {
        $site_slug = $this->site->slug();
        $path = "/s/$site_slug/document";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Index');
        $this->assertActionName('items-browse');

        $this->assertQueryContentContains('#content > h2', 'Items');
    }
}
