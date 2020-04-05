<?php
namespace CleanUrl;

// This file is copied in the main config folder, beside database.ini and local.config.php.

// IMPORTANT
// The copied file must be kept writeable, because it is automatically updated
// when a site slug is added or modified, or when a setting is updated.

/**
 * @todo Remove the "s", that is not required, but kept for now for modules and themes that don't use helper url(). Anyway it's not working.
 * @todo Create the list dynamically from the routes.
 */
const SLUGS_CORE = 'item|item-set|media|page|api|api-context|admin|asset|login|logout|create-password|forgot-password|maintenance|migrate|install|files|s|index|job|log|module|property|resource|resource-class|resource-template|setting|site|system-info|task|user|value|vocabulary';

/**
 * Allows to have a main site url without "/s/site-slug".
 */
const SLUG_MAIN_SITE = false;

/**
 * Hard coded.
 */
const SLUG_SITE_DEFAULT = 's/';

/**
 * Rename or remove /s/ from the urls of sites.
 * It must end with "/", or be an empty string. Default: "s/".
 */
const SLUG_SITE = 's/';

/**
 * Rename or remove /page/ from the urls of pages.
 * It must end with "/", or be an empty string. Default: "page/".
 */
const SLUG_PAGE = 'page/';

/**
 * Allows to have site urls without "s/" and page urls without "page/".
 */
const SLUGS_SITE = '';
