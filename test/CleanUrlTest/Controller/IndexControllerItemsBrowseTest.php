<?php

namespace CleanUrlTest\Controller;

class IndexControllerItemsBrowseTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => '',
            'cleanurl_media_allowed' => [],
            'cleanurl_item_allowed' => ['generic'],
            'cleanurl_media_generic' => '',
            'cleanurl_item_generic' => 'document/',
        ];
    }

    public function testRouteItemsBrowseAction()
    {
        $site_slug = $this->site->slug();
        $path = "/s/$site_slug/document";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\CleanUrl\Controller\Site\CleanUrlController::class);
        $this->assertActionName('items-browse');

        $this->assertQueryContentContains('#content > h2', 'Items');
    }
}
