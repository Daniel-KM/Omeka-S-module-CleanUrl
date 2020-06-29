<?php
namespace CleanUrl;

// This file is copied in the main config folder, beside database.ini and local.config.php.

/**
 * List of reserved slugs that cannot be used as site slug or page slug.
 * It is larger than needed in order to manage future modules or improvments,
 * according to existing modules in Omeka classic or Omeka S or common wishes.
 * It completes the default list present in Omeka core at top and site levels
 * (admin, api, item, login, etc.).
 * It can be edited as needed. This regex pattern must start with a "|".
 */
const SLUGS_RESERVED = '|access|adminer|annotation|ark|ark%3A|ark:|atom|auth|basket|bulk|bulk-check|bulk-import|bulk-export|cartography|collecting|comment|correction|cron|csvimport|custom-ontology|custom-vocab|download|ebook|edition|elastic|elastic-search|embed|embed-item|embed-page|epub|export|favorite|feed|find|graph|guest|iiif|iiif-img|iiif-search|iiif-server|image-server|import|ixif|ixif-media|ldap|login-admin|map|map-browse|ns|oai|oai-pmh|oauth|output|pdf|rss|saml|scripto|search|search-manager|selection|sitemap|solr|statistics|stats|story|storymap|subscription|tag|tagging|tags|timeline|unapi|upload|uri-dereferencer|value-suggest|vocab-suggest|xml-sitemap|zotero|zotero-import';
