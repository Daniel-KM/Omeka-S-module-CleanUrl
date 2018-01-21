<?php

namespace CleanUrlTest\Controller;

class IndexControllerItemSetShowTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => 'collection/',
            'cleanurl_media_generic' => 'media/',
            'cleanurl_media_allowed' => ['generic', 'generic_item', 'item_set', 'item_set_item'],
            'cleanurl_item_allowed' => ['generic'],
        ];
    }

    public function testItemSetShowAction()
    {
        $site_slug = $this->site->slug();
        $item_set_identifier = $this->item_set->value('dcterms:identifier');
        $path = "/s/$site_slug/collection/$item_set_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Site\Index');
        $this->assertActionName('item-set-show');
        $this->assertQueryContentContains('#content > h2', 'Item Set 1');
    }

    public function testItemSetShowActionForSecondItemSet()
    {
        $site_slug = $this->site->slug();
        $item_set_identifier = $this->item_set_2->value('dcterms:identifier');
        $path = "/s/$site_slug/collection/$item_set_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName('CleanUrl\Controller\Site\Index');
        $this->assertActionName('item-set-show');
        $this->assertQueryContentContains('#content > h2', 'Item Set 2');
    }
}
