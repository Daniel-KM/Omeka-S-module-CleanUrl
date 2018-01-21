<?php

namespace CleanUrl\Controller\Site;

use CleanUrl\Controller\AbstractCleanUrlController;

class CleanUrlController extends AbstractCleanUrlController
{
    protected $space = '__SITE__';
    protected $namespace = 'Omeka\Controller\Site';
    protected $namespaceItemSet = 'Omeka\Controller\Site\ItemSet';
    protected $namespaceItem = 'Omeka\Controller\Site\Item';
    protected $namespaceMedia = 'Omeka\Controller\Site\Media';
}
