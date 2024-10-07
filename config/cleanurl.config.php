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
const SLUGS_RESERVED = '|access|adminer|annotation|ark%3A|ark:|ark|atom|auth|basket|bulk-check|bulk-import|bulk-export|bulk|cartography|collecting|comment|correction|cron|css-editor|csvimport|custom-ontology|custom-vocab|download|ebook|edition|elastic-search|elastic|embed-item|embed-page|embed|epub|export|favorite|feed|find|graph|guest|iiif-img|iiif-presentation|iiif-search|iiif-server|iiif|image-server|import|item-sets-tree|ixif|ixif-media|ldap|login-admin|map-browse|map|ns|oai-pmh|oai|oauth|output|pdf|rss|saml|scripto|search-manager|search|selection|sitemap|solr|sso|statistics|stats|storymap|story|subscription|tagging|tags|tag|timeline|unapi|upload|uri-dereferencer|value-annotation|value-suggest|vocab-suggest|xml-sitemap|zip|zotero-import|zotero';

/**
 * @todo Remove the "s", that is not required, but kept for now for modules and themes that don't use helper url(). Anyway it's not working.
 * @todo Create the list dynamically from the routes.
 */
const SLUGS_CORE = 'item-set|item|media|page|api-context|api-local|api|admin|asset|login|logout|create-password|forgot-password|maintenance|migrate|install|files|cross-site-search|s|index|job|log|module|property|resource-class|resource-template|resource|setting|site|system-info|task|user|value|vocabulary';

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
