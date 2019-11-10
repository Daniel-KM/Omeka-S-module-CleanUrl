<?php
namespace CleanUrl\Controller\Site;

use const CleanUrl\MAIN_SITE_SLUG;

use Zend\View\Model\ViewModel;

class PageController extends \Omeka\Controller\Site\PageController
{
    public function showAction()
    {
        $site = $this->currentSite();
        $pageSlug = $this->params('page-slug');

        if (MAIN_SITE_SLUG && !$pageSlug) {
            // @see \Omeka\Controller\Site\IndexController::indexAction()
            // Display the configured homepage, if it exists.
            $page = $site->homepage();
            if (!$page) {
                // Display the first linked page, if it exists.
                $linkedPages = $site->linkedPages();
                if ($linkedPages) {
                    $page = current($linkedPages);
                }
            }
        } else {
            $page = $this->api()->read('site_pages', [
                'slug' => $this->params('page-slug'),
                'site' => $site->id(),
            ])->getContent();
        }

        $this->viewHelpers()->get('sitePagePagination')->setPage($page);

        $view = new ViewModel;
        $view
            ->setTemplate('omeka/site/page/show')
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
