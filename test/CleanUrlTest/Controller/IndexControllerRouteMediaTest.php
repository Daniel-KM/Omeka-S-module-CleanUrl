<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

class IndexControllerRouteMediaTest extends CleanUrlControllerTestCase
{
    protected function getSettings()
    {
        return [
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_main_path' => '',
            'cleanurl_item_set_generic' => '',
            'cleanurl_media_allowed' => ['generic'],
            'cleanurl_item_allowed' => ['item_set'],
            'cleanurl_media_generic' => '',
        ];
    }

    public function testRouteMediaAction(): void
    {
        $site_slug = $this->site->slug();
        $media = $this->item->media();
        $media_identifier = $media[0]->value('dcterms:identifier');
        $path = "/s/$site_slug/$media_identifier";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\CleanUrl\Controller\Site\CleanUrlController::class);
        $this->assertActionName('route-media');

        $this->assertQueryContentContains('#content > h2', $this->media_url);
    }
}
