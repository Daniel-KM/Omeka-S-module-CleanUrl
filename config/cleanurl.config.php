<?php declare(strict_types=1);

namespace CleanUrl;

// IMPORTANT
// The directory config/ of Omeka must be kept writeable, because this file is
// automatically updated when a site slug is added or modified, or when a
// setting is updated.

/**
 * List of reserved slugs that cannot be used as site slug or page slug.
 * It is larger than needed in order to manage future modules or improvments,
 * according to existing modules in Omeka classic or Omeka S or common wishes.
 * It completes the default list present in Omeka core at top and site levels
 * (admin, api, item, login, etc.).
 * It can be edited as needed. This regex pattern must start with a "|".
 */
const SLUGS_RESERVED = '|access|adminer|annotation|ark|ark%3A|ark:|atom|auth|basket|bulk|bulk-check|bulk-import|bulk-export|cartography|collecting|comment|correction|cron|csvimport|custom-ontology|custom-vocab|download|ebook|edition|elastic|elastic-search|embed|embed-item|embed-page|epub|export|favorite|feed|find|graph|guest|iiif|iiif-img|iiif-search|iiif-server|image-server|import|ixif|ixif-media|ldap|login-admin|map|map-browse|ns|oai|oai-pmh|oauth|output|pdf|rss|saml|scripto|search|search-manager|selection|sitemap|solr|statistics|stats|story|storymap|subscription|tag|tagging|tags|timeline|unapi|upload|uri-dereferencer|value-suggest|vocab-suggest|xml-sitemap|zotero|zotero-import';

/**
 * @todo Remove the "s", that is not required, but kept for now for modules and themes that don't use helper url(). Anyway it's not working.
 * @todo Create the list dynamically from the routes.
 */
const SLUGS_CORE = 'item|item-set|media|page|api|api-context|admin|asset|login|logout|create-password|forgot-password|maintenance|migrate|install|files|cross-site-search|s|index|job|log|module|property|resource|resource-class|resource-template|setting|site|system-info|task|user|value|vocabulary';

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
