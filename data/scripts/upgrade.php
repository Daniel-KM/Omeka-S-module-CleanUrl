<?php declare(strict_types=1);

namespace CleanUrl;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = @require dirname(__DIR__, 2) . '/config/module.config.php';
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

    if (!method_exists($this, 'preInstallCopyConfigFiles')) {
        $message = new Message('Your previous version is too old to do a direct upgrade to the current version. Upgrade to version 3.15.13 first, or uninstall/reinstall the module.'); // @translate
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

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

if (version_compare($oldVersion, '3.16.0.3', '<')) {
    if (!$this->isConfigWriteable()) {
        $message = new Message('The file "config/cleanurl.config.php" at the root of Omeka is not writeable.'); // @translate
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

    $message = new Message(
        'The module has been rewritten and the whole configuration has been simplified. You should check your config, because the upgrade of the configuration is not automatic.' // @translate
    );
    $message->setEscapeHtml(false);
    $messenger = new Messenger();
    $messenger->addWarning($message);

    if (file_exists(OMEKA_PATH . '/config/clean_url.config.php')) {
        @rename(OMEKA_PATH . '/config/clean_url.config.php', OMEKA_PATH . '/config/clean_url.config.old.php');
    }
    if (file_exists(OMEKA_PATH . '/config/clean_url.dynamic.php')) {
        @rename(OMEKA_PATH . '/config/clean_url.dynamic.php', OMEKA_PATH . '/config/clean_url.dynamic.old.php');
    }

    $settings->delete('cleanurl_item_set_default');
    $settings->delete('cleanurl_item_default');
    $settings->delete('cleanurl_media_default');
}

if (version_compare($oldVersion, '3.16.1.3', '<')) {
    if (!$this->isConfigWriteable()) {
        $message = new Message('The file "config/cleanurl.config.php" at the root of Omeka is not writeable.'); // @translate
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

    $module = $services->get('Omeka\ModuleManager')->getModule('Generic');
    if ($module && version_compare($module->getIni('version') ?? '', '3.3.27', '<')) {
        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
            'Generic', '3.3.27'
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

    // Manage upgrade from old version.
    if (version_compare($oldVersion, '3.16.0.3', '<')) {
        $main = '';
        $main1 = trim($settings->get('cleanurl_main_path', ''), '/');
        $main .= mb_strlen($main1) ? $main1 . '/' : '';
        $main2 = trim($settings->get('cleanurl_main_path_2', ''), '/');
        $main .= mb_strlen($main2) ? $main2 . '/' : '';
        $main3 = trim($settings->get('cleanurl_main_path_3', ''), '/');
        $main .= mb_strlen($main3) ? $main3 . '/' : '';
        $defaults = [
            'item_set' => $main . '{item_set_identifier}',
            'item' => $main . '{item_identifier}',
            'media' => $main . '{item_identifier}/{media_id}',
        ];
        $patterns = [
            'item_set' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'item' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'media' => '',
        ];
        foreach (['item_set', 'item', 'media'] as $resourceType) {
            $options = [
                'default' => $defaults[$resourceType],
                'short' => '',
                'paths' => [],
                'pattern' => $patterns[$resourceType],
                'pattern_short' => '',
                'property' => $settings->get('cleanurl_identifier_property', 10),
                'prefix' => $settings->get('cleanurl_identifier_prefix', ''),
                'prefix_part_of' => $settings->get('cleanurl_identifier_prefix_part_of', false),
                'keep_slash' => (bool) $settings->get("cleanurl_{$resourceType}_keep_raw", false),
                'case_sensitive' => $settings->get('cleanurl_identifier_case_sensitive', false),
                ];
            $settings->set("cleanurl_$resourceType", $options);
        }
    }
    // Manage upgrade for 3.16.0.3.
    else {
        foreach (['item_set', 'item', 'media'] as $resourceType) {
            $options = [
                'default' => $settings->get("cleanurl_{$resourceType}_default", ''),
                'short' => $settings->get("cleanurl_{$resourceType}_short", ''),
                'paths' => $settings->get("cleanurl_{$resourceType}_paths", []),
                'pattern' => $settings->get("cleanurl_{$resourceType}_pattern", ''),
                'pattern_short' => $settings->get("cleanurl_{$resourceType}_pattern_short", ''),
                'property' => $settings->get('cleanurl_identifier_property', 10),
                'prefix' => $settings->get('cleanurl_identifier_prefix', ''),
                'prefix_part_of' => $settings->get('cleanurl_identifier_prefix_part_of', false),
                'keep_slash' => (bool) $settings->get('cleanurl_identifier_keep_slash', false),
                'case_sensitive' => $settings->get('cleanurl_identifier_case_sensitive', false),
            ];
            $settings->set("cleanurl_$resourceType", $options);
        }
    }

    $removed = [
        // older.
        'cleanurl_identifier_unspace',
        'cleanurl_identifier_undefined',
        'cleanurl_main_path',
        'cleanurl_main_path_2',
        'cleanurl_main_path_3',
        'cleanurl_main_short',
        'cleanurl_item_set_generic',
        'cleanurl_item_set_keep_raw',
        'cleanurl_item_allowed',
        'cleanurl_item_generic',
        'cleanurl_item_keep_raw',
        'cleanurl_item_item_set_included',
        'cleanurl_item_item_set_undefined',
        'cleanurl_media_allowed',
        'cleanurl_media_generic',
        'cleanurl_media_keep_raw',
        'cleanurl_media_item_set_included',
        'cleanurl_media_item_included',
        'cleanurl_media_item_set_undefined',
        'cleanurl_media_item_undefined',
        'cleanurl_media_media_undefined',
        'cleanurl_media_format_position',
        // 3.16.0.3.
        'cleanurl_identifier_property',
        'cleanurl_identifier_prefix',
        'cleanurl_identifier_short',
        'cleanurl_identifier_prefix_part_of',
        'cleanurl_identifier_case_sensitive',
        'cleanurl_identifier_keep_slash',
        'cleanurl_item_set_paths',
        'cleanurl_item_set_default',
        'cleanurl_item_set_short',
        'cleanurl_item_set_pattern',
        'cleanurl_item_set_pattern_short',
        'cleanurl_item_paths',
        'cleanurl_item_default',
        'cleanurl_item_short',
        'cleanurl_item_pattern',
        'cleanurl_item_pattern_short',
        'cleanurl_media_paths',
        'cleanurl_media_default',
        'cleanurl_media_short',
        'cleanurl_media_pattern',
        'cleanurl_media_pattern_short',
        'cleanurl_quick_settings',
    ];
    foreach ($removed as $name) {
        $settings->delete($name);
    }

    if (file_exists(OMEKA_PATH . '/config/clean_url.config.php')) {
        @unlink(OMEKA_PATH . '/config/clean_url.config.php');
    }
    if (file_exists(OMEKA_PATH . '/config/clean_url.config.old.php')) {
        @unlink(OMEKA_PATH . '/config/clean_url.config.old.php');
    }
    if (file_exists(OMEKA_PATH . '/config/clean_url.dynamic.php')) {
        @unlink(OMEKA_PATH . '/config/clean_url.dynamic.php');
    }
    if (file_exists(OMEKA_PATH . '/config/clean_url.dynamic.old.php')) {
        @unlink(OMEKA_PATH . '/config/clean_url.dynamic.old.php');
    }

    $this->cacheCleanData();
    $this->cacheRouteSettings();
}

if (version_compare($oldVersion, '3.16.3.3', '<')) {
    $this->getConfig();
    $this->cacheCleanData();
    $this->cacheRouteSettings();
}
