<?php

namespace CleanUrlTest\Controller;

class IndexControllerRouteMediaTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'clean_url_identifier_property' => 10,
            'clean_url_identifier_prefix' => '',
            'clean_url_main_path' => '',
            'clean_url_item_set_generic' => '',
            'clean_url_media_allowed' => ['generic'],
            'clean_url_item_allowed' => ['item_set'],
            'clean_url_media_generic' => '',
        ];
    }

    public function testRouteMediaAction()
    {
        $site_slug = $this->site->slug();
        $media = $this->item->media();
        $media_identifier = $media[0]->value('dcterms:identifier');
        $path = "/s/$site_slug/$media_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Index');
        $this->assertActionName('route-media');

        $this->assertQueryContentContains('#content > h2', $this->media_url);
    }
}
