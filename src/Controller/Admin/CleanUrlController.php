<?php

namespace CleanUrl\Controller\Admin;

use CleanUrl\Controller\AbstractCleanUrlController;

class CleanUrlController extends AbstractCleanUrlController
{
    protected $space = '__ADMIN__';
    protected $namespace = 'Omeka\Controller\Admin';
    protected $namespaceItemSet = 'Omeka\Controller\Admin\ItemSet';
    protected $namespaceItem = 'Omeka\Controller\Admin\Item';
    protected $namespaceMedia = 'Omeka\Controller\Admin\Media';
}
