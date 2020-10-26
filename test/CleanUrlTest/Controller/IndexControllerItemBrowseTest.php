<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

class IndexControllerItemBrowseTest extends CleanUrlControllerTestCase
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

    public function testRouteItemBrowseAction(): void
    {
        $site_slug = $this->site->slug();
        $path = "/s/$site_slug/document";
        $this->dispatch($path);

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\CleanUrl\Controller\Site\CleanUrlController::class);
        $this->assertActionName('route-item-browse');

        $this->assertQueryContentContains('#content > h2', 'Items');
    }
}
