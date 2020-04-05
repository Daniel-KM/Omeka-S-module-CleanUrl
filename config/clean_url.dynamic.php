<?php
namespace CleanUrl;

// This file is copied in the main config folder, beside database.ini and local.config.php.

// IMPORTANT
// The copied file must be kept writeable, because it is automatically updated
// when a site slug is added or updated.

// TODO Remove the "s", that is not required, but kept for now for modules and themes that don't use helper url(). Anyway it's not working.
const SLUGS_CORE = 'item|item-set|media|page|api|api-context|admin|login|logout|create-password|forgot-password|maintenance|migrate|install|files|s';

const SLUG_SITE_DEFAULT = 's/';

const SLUGS_SITE = '';
