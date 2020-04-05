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
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

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
    $settings->set('cleanurl_use_admin',
        $config[strtolower(__NAMESPACE__)]['config']['cleanurl_use_admin']);
}

if (version_compare($oldVersion, '3.15.13', '<')) {
    $oldPath = OMEKA_PATH . '/config/routes.main_slug.php';
    $newPath = OMEKA_PATH . '/config/clean_url.config.php';
    if (file_exists($oldPath) && !file_exists($newPath)) {
        $result = @rename($oldPath, $newPath);
        if ($result) {
            $content = file_get_contents($newPath);
            if (strpos($content, 'SLUG_MAIN_SITE') === false) {
                $content .= PHP_EOL . PHP_EOL
                    . '// MAIN_SITE_SLUG is deprecated.' . PHP_EOL
                    . 'const SLUG_MAIN_SITE = MAIN_SITE_SLUG;' . PHP_EOL;
                $result = file_put_contents($newPath, $content);
                if (!$result) {
                    $t = $services->get('MvcTranslator');
                    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                    $messenger->addWarning($t->translate('Automatic update of main config failed. You should rename the constant "MAIN_SITE_SLUG" to "SLUG_MAIN_SITE" in the file "config/clean_url.config.php" in the config directory of Omeka.')); // @translate
                }
            }
        } else {
            $t = $services->get('MvcTranslator');
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messenger->addWarning($t->translate('Automatic renaming failed. You should rename manually "config/routes.main_slug.php" as "config/clean_url.config.php" in the config directory of Omeka.')); // @translate
        }
    }
}
