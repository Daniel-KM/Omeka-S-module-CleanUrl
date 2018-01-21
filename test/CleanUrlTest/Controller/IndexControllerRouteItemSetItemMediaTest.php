<?php

namespace CleanUrlTest\Controller;

class IndexControllerRouteItemSetItemMediaTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => 'collection/',
            'cleanurl_media_allowed' => ['generic', 'generic_item', 'item_set', 'item_set_item'],
            'cleanurl_item_allowed' => ['generic'],
        ];
    }

    public function testRouteItemSetItemMediaAction()
    {
        $site_slug = $this->site->slug();
        $item_set_identifier = $this->item_set->value('dcterms:identifier');
        $item_identifier = $this->item->value('dcterms:identifier');
        $media = $this->item->media();
        $media_identifier = $media[0]->value('dcterms:identifier');
        $path = "/s/$site_slug/collection/$item_set_identifier/$item_identifier/$media_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Site\Index');
        $this->assertActionName('route-item-set-item-media');

        $this->assertQueryContentContains('#content > h2', $this->media_url);
    }
}
