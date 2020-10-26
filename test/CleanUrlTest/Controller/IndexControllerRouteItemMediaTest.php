<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

class IndexControllerRouteItemMediaTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => '',
            'cleanurl_media_allowed' => ['generic_item'],
            'cleanurl_item_allowed' => ['item_set'],
            'cleanurl_media_generic' => '',
            'cleanurl_item_generic' => '',
        ];
    }

    public function testRouteItemMediaAction(): void
    {
        $site_slug = $this->site->slug();
        $item_identifier = $this->item->value('dcterms:identifier');
        $media = $this->item->media();
        $media_identifier = $media[0]->value('dcterms:identifier');
        $path = "/s/$site_slug/$item_identifier/$media_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\CleanUrl\Controller\Site\CleanUrlController::class);
        $this->assertActionName('route-item-media');

        $this->assertQueryContentContains('#content > h2', $this->media_url);
    }
}
