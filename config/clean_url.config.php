<?php
namespace CleanUrl;

// This file is copied in the main config folder, beside database.ini and local.config.php.

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
 * List of reserved slugs that cannot be used as site slug or page slug.
 * It is larger than needed in order to manage future modules or improvments,
 * according to existing modules in Omeka classic or Omeka S or common wishes.
 * It completes the default list present in Omeka core at top and site levels
 * (admin, api, item, login, etc.).
 * It can be edited as needed. This regex pattern must start with a "|".
 */
const SLUGS_RESERVED = '|access|adminer|annotation|ark|ark%3A|ark:|atom|auth|basket|bulk|cartography|collecting|comment|correction|cron|download|ebook|elastic|elastic-search|embed|embed-item|embed-page|epub|export|favorite|feed|find|graph|guest|iiif|iiif-img|iiif-search|iiif-server|image-server|import|ldap|login-admin|map|map-browse|ns|oai|oai-pmh|oauth|output|pdf|rss|saml|scripto|search|sitemap|solr|statistics|stats|story|storymap|subscription|tag|tagging|tags|timeline|unapi|upload|uri-dereferencer|value-suggest|xml-sitemap';
