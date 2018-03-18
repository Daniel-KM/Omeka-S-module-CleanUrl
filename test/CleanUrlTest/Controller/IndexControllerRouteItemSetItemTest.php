<?php

namespace CleanUrlTest\Controller;

class IndexControllerRouteItemSetItemTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => 'collection/',
            'cleanurl_media_allowed' => ['generic', 'generic_item', 'item_set_item'],
            'cleanurl_item_allowed' => ['generic', 'item_set'],
        ];
    }

    public function testRouteItemSetItemAction()
    {
        $site_slug = $this->site->slug();
        $item_set_identifier = $this->item_set->value('dcterms:identifier');
        $item_identifier = $this->item->value('dcterms:identifier');
        $path = "/s/$site_slug/collection/$item_set_identifier/$item_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\CleanUrl\Controller\Site\CleanUrlController::class);
        $this->assertActionName('route-item-set-item');

        $this->assertQueryContentContains('#content > h2', 'Item 1');
    }
}
