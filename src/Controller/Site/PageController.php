<?php declare(strict_types=1);

namespace CleanUrl\Controller\Site;

use Laminas\Mvc\Exception\RuntimeException;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Exception as ApiException;
use Omeka\Api\Representation\SiteRepresentation;

class PageController extends \Omeka\Controller\Site\PageController
{
    public function showAction()
    {
        /**
         * Merge of parent method and index controller to manage the case where
         * the slug is skipped.
         *
         * View helper currentSite() (Module Next) was integrated in Omeka v4.
         * But there is a core issue in core MvcListeners.
         *
         * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
         * @see \Omeka\Controller\IndexController::indexAction()
         * @see \Omeka\Controller\Site\IndexController::indexAction()
         * @see \Omeka\Controller\Site\PageController::showAction()
         */

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $this->currentSite();

        /**
         * Fix issue without site slug.
         * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
         *
         * @var \Omeka\Mvc\Status $status
         */
        $siteSlug = $this->status()->getRouteMatch()->getParam('site-slug');
        $isSiteSlug = is_string($siteSlug) && strlen($siteSlug);
        if ($site && !$isSiteSlug) {
            $site = null;
        }

        // @see \Omeka\Controller\Site\PageController::indexAction()
        $slug = $this->params('page-slug');
        $isPageSlug = is_string($slug) && strlen($slug);
        if ($isPageSlug) {
            if (!$site) {
                $site = $this->defaultSite();
                if (!$site) {
                    throw new RuntimeException('Cannot render page: no default site was set.'); // @translate
                }
                // Here, the default site was not prepared in core MvcListeners,
                // so redirect.
                return $this->redirect()->toRoute('site/page', ['site-slug' => $site->slug(), 'page-slug' => $slug]);
            }
            $page = $this->api()->read('site_pages', [
                'slug' => $slug,
                'site' => $site->id(),
            ])->getContent();
        } elseif (!$site) {
            // Redirect to default site when set, else list sites.
            $site = $this->defaultSite();
            if ($site) {
                return $this->redirect()->toUrl($site->siteUrl());
            }
            // Display the list of sites.
            $this->setBrowseDefaults('title', 'asc');
            $response = $this->api()->search('sites', $this->params()->fromQuery());
            $this->paginator($response->getTotalResults());
            $view = new ViewModel([
                'sites' => $response->getContent(),
            ]);
            return $view
                ->setTemplate('omeka/index/index');
        } else {
            // Redirect to the configured homepage, if it exists.
            $page = $site->homepage();
            if (!$page) {
                // Redirect to first linked page if any, else to list of sites.
                $linkedPages = $site->linkedPages();
                if (!count($linkedPages)) {
                    $view = new ViewModel([
                        'site' => $site,
                    ]);
                    return $view
                        ->setTemplate('omeka/site/index/index');
                }
                $page = current($linkedPages);
            }
        }

        if ($isPageSlug) {
            $pageBodyClass = 'page site-page-' . preg_replace('([^a-zA-Z0-9\-])', '-', $slug);
        } else {
            $pageBodyClass = 'page site-page';
        }

        $this->viewHelpers()->get('sitePagePagination')->setPage($page);

        $view = new ViewModel([
            'site' => $site,
            'page' => $page,
            'pageBodyClass' => $pageBodyClass,
            'displayNavigation' => true,
        ]);

        $contentView = clone $view;
        $contentView
            ->setTemplate('omeka/site/page/content')
            ->setVariable('pageViewModel', $view);

        return $view
            ->addChild($contentView, 'content');
    }

    protected function defaultSite(): ?SiteRepresentation
    {
        $defaultSiteId = (int) $this->settings()->get('default_site');
        if ($defaultSiteId) {
            try {
                return $this->api()->read('sites', ['id' => $defaultSiteId])->getContent();
            } catch (ApiException\NotFoundException $e) {
                // Nothing.
            }
        }
        return null;
    }
}
