<?php declare(strict_types=1);
namespace CleanUrl\Controller\Site;

use Laminas\View\Model\ViewModel;

class PageController extends \Omeka\Controller\Site\PageController
{
    public function showAction()
    {
        $view = new ViewModel;

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $this->currentSite();

        // @see \Omeka\Controller\Site\PageController::indexAction()
        $pageSlug = $this->params('page-slug');
        if ($pageSlug) {
            $page = $this->api()->read('site_pages', [
                'slug' => $pageSlug,
                'site' => $site->id(),
            ])->getContent();
        }

        // @see \Omeka\Controller\Site\IndexController::indexAction()
        else {
            // Redirect to the configured homepage, if it exists.
            $page = $site->homepage();
            if (!$page) {
                // Redirect to first linked page if any, else to list of sites.
                $linkedPages = $site->linkedPages();
                if (!count($linkedPages)) {
                    return $view
                        ->setTemplate('omeka/site/index/index')
                        ->setVariable('site', $site);
                }
                $page = current($linkedPages);
            }
        }

        // Copy of parent method.

        $this->viewHelpers()->get('sitePagePagination')->setPage($page);

        $view
            ->setVariable('site', $site)
            ->setVariable('page', $page)
            ->setVariable('displayNavigation', true);

        $contentView = clone $view;
        $contentView
            ->setTemplate('omeka/site/page/content')
            ->setVariable('pageViewModel', $view);

        $view->addChild($contentView, 'content');
        return $view;
    }
}
