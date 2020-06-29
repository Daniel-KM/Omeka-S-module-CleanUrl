<?php
namespace CleanUrl;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = @require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');

if (version_compare($oldVersion, '3.14', '<')) {
    $settings->set('clean_url_identifier_property',
        (int) $settings->get('clean_url_identifier_property'));

    $settings->set('clean_url_item_allowed',
        unserialize($settings->get('clean_url_item_allowed')));
    $settings->set('clean_url_media_allowed',
        unserialize($settings->get('clean_url_media_allowed')));

    $this->cacheItemSetsRegex($serviceLocator);
}

if (version_compare($oldVersion, '3.15.3', '<')) {
    foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
        $oldName = str_replace('cleanurl_', 'clean_url_', $name);
        $settings->set($name, $settings->get($oldName, $value));
        $settings->delete($oldName);
    }
}

if (version_compare($oldVersion, '3.15.5', '<')) {
    $settings->set('cleanurl_admin_use',
        $config[strtolower(__NAMESPACE__)]['config']['cleanurl_admin_use']);
}

if (version_compare($oldVersion, '3.15.13', '<')) {
    $t = $services->get('MvcTranslator');
    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;

    if (!$this->preInstallCopyConfigFiles()) {
        $message = $t->translate('Unable to copy config files "config/clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka.'); // @translate
        $messenger->addWarning($message);
        $logger = $services->get('Omeka\Logger');
        $logger->err('The file "clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka is not writeable.'); // @translate
    }

    $messenger->addWarning($t->translate('Check the new config file "config/clean_url.config.php" and remove the old one in the config directory of Omeka.')); // @translate

    $settings->set('cleanurl_site_skip_main', false);
    $settings->set('cleanurl_site_slug', 's/');
    $settings->set('cleanurl_page_slug', 'page/');

    $settings->set('cleanurl_identifier_case_insensitive', $settings->get('cleanurl_case_insensitive'));
    $settings->delete('cleanurl_case_insensitive');

    $settings->set('cleanurl_admin_show_identifier', $settings->get('cleanurl_display_admin_show_identifier'));
    $settings->delete('cleanurl_display_admin_show_identifier');

    $settings->set('cleanurl_admin_use', $settings->get('cleanurl_use_admin'));
    $settings->delete('cleanurl_use_admin');

    $settings->set('cleanurl_item_default', $settings->get('cleanurl_item_default') . '_item');
    $routes = [];
    foreach ($settings->get('cleanurl_item_allowed') as $route) {
        $routes[] = $route . '_item';
    }
    $settings->set('cleanurl_item_allowed', $routes);

    $settings->set('cleanurl_media_default', $settings->get('cleanurl_media_default') . '_media');
    $routes = [];
    foreach ($settings->get('cleanurl_media_allowed') as $route) {
        $routes[] = $route . '_media';
    }
    $settings->set('cleanurl_media_allowed', $routes);

    $mainPath = $settings->get('cleanurl_main_path');
    $settings->set('cleanurl_main_path_full', $mainPath);
    if ($mainPath) {
        $settings->set('cleanurl_main_path_full_encoded', $this->encode(rtrim($mainPath, '/')) . '/');
    }

    $settings->set('cleanurl_main_short', false);

    $settings->set('cleanurl_item_item_set_included', 'no');
    $settings->set('cleanurl_media_item_set_included', 'no');
    $settings->set('cleanurl_media_item_included', 'no');

    $settings->set('cleanurl_regex', $this->prepareRegexes([
        'main_path_full' => $settings->get('cleanurl_main_path_full', ''),
        'item_set_generic' => $settings->get('cleanurl_item_set_generic', ''),
        'item_generic' => $settings->get('cleanurl_item_generic', ''),
        'media_generic' => $settings->get('cleanurl_media_generic', ''),
    ]));

    $this->cacheCleanData();
    $this->cacheItemSetsRegex();
}

if (version_compare($oldVersion, '3.15.15', '<')) {
    $mainShort = $settings->get('cleanurl_main_short');
    $settings->set('cleanurl_main_short', $mainShort ? 'main' : 'no');

    $settings->delete('cleanurl_main_path_full');
    $settings->delete('cleanurl_main_path_full_encoded');
    $settings->delete('cleanurl_main_short_path_full');
    $settings->delete('cleanurl_main_short_path_full_encoded');
    $settings->delete('cleanurl_main_short_path_full_regex');
    $settings->delete('cleanurl_item_set_regex');
    $settings->delete('cleanurl_regex');

    $this->cacheRouteSettings();
    $this->cacheCleanData();
    $this->cacheItemSetsRegex();
}

if (version_compare($oldVersion, '3.15.17', '<')) {
    $source = __DIR__ . '/../../config/clean_url.config.php';
    $dest = OMEKA_PATH . '/config/clean_url.config.php';
    $t = $services->get('MvcTranslator');
    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
    $logger = $services->get('Omeka\Logger');
    if (!is_readable($source) || !is_writeable(dirname($dest)) || !is_writeable($dest)) {
        $message = $t->translate('Unable to copy config files "config/clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka.'); // @translate
        $messenger->addWarning($message);
        $logger->err('The file "clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka is not writeable.'); // @translate
    } else {
        copy($source, $dest);
    }

    $settings->set('cleanurl_identifier_case_sensitive', (bool) $settings->get('cleanurl_identifier_case_insensitive'));
    $settings->delete('cleanurl_identifier_case_insensitive');
}
