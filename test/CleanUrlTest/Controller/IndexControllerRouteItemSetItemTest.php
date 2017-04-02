<?php

namespace CleanUrlTest\Controller;

class IndexControllerRouteItemSetItemTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'clean_url_identifier_property' => 10,
            'clean_url_identifier_prefix' => '',
            'clean_url_main_path' => '',
            'clean_url_item_set_generic' => 'collection/',
            'clean_url_media_allowed' => ['generic', 'generic_item', 'item_set_item'],
            'clean_url_item_allowed' => ['generic', 'item_set'],
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
        $this->assertControllerName('CleanUrl\Controller\Index');
        $this->assertActionName('route-item-set-item');

        $this->assertQueryContentContains('#content > h2', 'Item 1');
    }
}
