<?php
namespace CleanUrl\Controller\Site;

use Zend\View\Model\ViewModel;

class PageController extends \Omeka\Controller\Site\PageController
{
    public function showAction()
    {
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
            $page = method_exists($site, 'homepage') ? $site->homepage() : null;
            if (!$page) {
                // Redirect to the first linked page, if it exists.
                $linkedPages = $site->linkedPages();
                if ($linkedPages) {
                    $page = current($linkedPages);
                } else {
                    $view = new ViewModel;
                    $view
                        ->setTemplate('omeka/site/index/index')
                        ->setVariable('site', $site);
                    return $view;
                }
            }
        }

        // Copy of parent method.

        $this->viewHelpers()->get('sitePagePagination')->setPage($page);

        $view = new ViewModel;
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
