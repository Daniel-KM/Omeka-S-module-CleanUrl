Clean Url (module for Omeka S)
==============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Clean Url] is a module for [Omeka S] that creates clean, readable and search
engine optimized URLs like `https://example.com/my_item_set/dc:identifier`
instead of `https://example.com/item/internal_code`. Used identifiers come from
standard Dublin Core metadata, or from any specific field, so they are easy to
manage. It supports [Ark] and short urls too.

Furthermore, it makes possible to use a main site and additional sites, like in
Omeka Classic, so the main site won’t start with "/s/site-slug". The slug "/page/"
can be removed too, or replaced by something else. The urls from Omeka Classic
can be recreated easily too, so old urls can still be alive.

This [Omeka S] module was initially based on a rewrite of the [Clean Url plugin]
for [Omeka] and provide the same features as the original plugin and many more.


Installation
------------

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

* From the zip

Download the last release [CleanUrl.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `CleanUrl`.

Then install it like any other Omeka module and follow the config instructions.

* For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/CleanUrl/phpunit.xml --testdox
```


Usage
-----

Clean urls are automatically displayed in public theme and they are not used in
the admin theme. They are case insensitive by default.

This module may be used with the module [Archive Repertory] to set similar paths
for real files (item_set_identifier / item_identifier / true_filename).

**IMPORTANT**: In all cases, it is recommended to use unique identifiers through
sites, pages, item, item set, media. and any other resources.

### Main site

In some cases, Omeka S is used like in Omeka Classic, with a main site and some
exhibits or decentralized sites (see [omeka/omeka-s#870]). In such cases, the
prefix "/s/site-slug" is useless and not seo and user friendly. An option is
available in the config form to remove it.

### Sites and pages

Options are available to replace or remove the `s/` and the `page/` in order to
get these urls:

    - / [ s/ ] :site-slug / [ page/ ] :page-slug
    - / [ s/ ] :site-slug / :page-slug
    - / :site-slug
    - / :page-slug (for main site)

Of course, be aware that some conflicts are possible in particular for pages,
even if some slugs are reserved. A check is done when creating sites and pages
to avoid issues.

### Identifiers

Simply set an identifier for each record in a field. The recommended field is
`Dublin Core:Identifier`.

- An identifier is always literal: it identifies a resource inside the base. It
  can't be an external uri or a linked resource.
- Identifiers can be any strings with any characters. Identifier are url-encoded
  according to the standard, but it is recommended to avoid characters like "%"
  or "$".
- To use numbers as identifier is possible but not recommended, because they can
  be confused with the internal id or resources. If so, it’s recommended that
  all records have got an identifier.
- A prefix can be added if you have other metadata in the same field.
- A record can have multiple identifiers. The first one will be used to set the
  default url. Other ones can be used to set alias.
- If the same identifier is used for multiple records, only the first record can
  be got. Currently, no check is done when duplicate identifiers are set.
- Reserved words like "item_sets", "items", "medias", sites and simple pages
  slugs...) should not be used as identifiers, except if there is a part before
  them (a main path, a item set identifier or a generic word).
- If not set, the identifier will be the default id of the record, except for
  item sets, where the original path will be used.
- If the path for the item contains the item set identifier, the first item set
  will be used. If none, the urls will be the standard one.

**Important**: the pattern is applied on the url, that is encoded, and not on
the raw value. So a character that is not encoded must be part of the pattern
to be usable: the identifier `test.output` requires a pattern like
`[a-zA-Z0-9][a-zA-Z0-9_.-]*`, else the resource keeps its default url. The
characters `- _ . ~` and `! $ & ' ( ) * + , : ; = @` are kept as is in the url,
and the slash too when the option "Identifiers have slash" is set. On the
contrary, a character that is encoded, like the space (`%20`) or an accented
letter (`%C3%A9`), can nearly never be matched by a simple pattern.

See below how to list the identifiers that have no clean url.

### Structure of urls

The configuration page let you choose the structure of paths for item sets,
items and files.

Each resource can have a default path, a short path, and additional paths, or
not. Multiple urls can be set, in particular to have a permalink and a search
engine optimized link. It is not recommended to multiply them.

Paths are simple string where you can set the type of identifier you want
between `{}`. Managed identifiers are:

- `item_set_id`
- `item_set_identifier`
- `item_set_identifier_short`
- `item_id`
- `item_identifier`
- `item_identifier_short`
- `media_id`
- `media_identifier`
- `media_identifier_short`
- `media_position`
- `digital_object_id` (only with module [Digital Object])
- `digital_object_identifier` (only with module [Digital Object])
- `digital_object_identifier_short` (only with module [Digital Object])
- `concept_id` (only with module [Thesaurus])
- `concept_identifier` (only with module [Thesaurus])
- `concept_identifier_short` (only with module [Thesaurus])

So an example for a document within an item set may be `collection/{item_set_identifier}/{item_identifier}`.

Note that if you choose to include the item set in the path, all items should
have an item set and all item set should have an identifier.

The identifier of the media can be the position. When used, it is recommended to
specify a format with a leading letter to avoid confusion with numeric media id,
for example `p{media_position}`. Furthermore, the position may not be stable: a
scanned image may be missing. Finally, if the first media is not marked "1" in
the database or if the positions are not the good one, use module [Bulk Check]
to fix them. Anyway, the identifier can be the content of any property, as long
as its content is unique for the list of media of the item.

### Digital objects

When the module [Digital Object] is installed, digital objects get their own
paths, with the same options than the other resources.

A digital object is a top-level resource: unlike a media, it has no parent
item, so its path cannot contain an item identifier. Only the standalone
schema is available, and the default path is
`digital-object/{digital_object_identifier}`.

### Concepts

When the module [Thesaurus] is installed, concepts get their own paths, with
the same options than the other resources. The default path is
`concept/{concept_identifier}` and the default property is `skos:notation`.

A concept is a top-level resource, so its path cannot contain an item or an
item set identifier. Furthermore, the module [Thesaurus] has no public
controller for concepts, so the clean urls are built for the admin board only.

### Canonical url

A resource is available through its clean url and through its default url, so
a search engine may index the same page twice. The option "Add a canonical
link to the clean url" adds a `<link rel="canonical">` to the clean url on the
public pages of item sets, items, medias, digital objects and site pages.
Nothing is added when the current url is already the clean one, or when the
resource has no clean url.

### Access a resource with its identifier

Two options, disabled by default, allow to reach a resource with its identifier
instead of its internal id.

The option "Allow to read the api with an identifier" makes the api answer to
`/api/items/{identifier}` as well as to `/api/items/{id}`. A numeric value is
always an id, so an identifier that is a number cannot be used. Only the read is
managed for now: an identifier is a metadata that can be edited or duplicated,
so it is not a safe target to create, update or delete a resource. The `@id` of
the json-ld output is unchanged, so the identity of the resources is the same
for the clients that already use it.

The option "Add the dereferenceable uri" publishes `/id/{identifier}` for each
resource. This uri depends neither on the type of the resource nor on the way it
is displayed, so it can be published as the identifier of the resource itself.
It redirects with a "303 See Other" to the page of the resource for a browser,
and to its json-ld description when the client asks for it (header `Accept` with
`application/ld+json` or `application/json`), according to the W3C note [Cool URIs].
The identifier may contain a "/", so an ark can be used: `/id/ark:/12345/bWZ4`.

### Urls built outside of a site

A site url requires the slug of the site, that is taken from the current
request. Outside of a site request, in particular in a background job run via
the cli, there is no such slug, so the slug of the default site is used, or the
one of the first site when no default site is set. Without it, the url could
not be built at all and the job would fail.

### Urls built by background jobs

Building a clean url requires to look for the identifier of the resource in the
database, so it costs about two hundred times more than a standard url. This is
fine to display a page, but not for a job that builds thousands of urls that
are never displayed, for example to serialize resources as json-ld.

Such a job may skip the clean urls during its process:

```php
$urlHelper = $services->get('ViewHelperManager')->get('url');
// The module may not be installed.
if (method_exists($urlHelper, 'setSkipCleanUrl')) {
    $urlHelper->setSkipCleanUrl(true);
}
// Process…
$urlHelper->setSkipCleanUrl(false);
```

The urls remain valid: only the clean form is skipped, so the standard url is
returned instead. The option should always be restored at the end of the
process, and it must not be used to build urls that are displayed or stored.

### Check of identifiers

An identifier that doesn’t match the pattern, or that is a reserved word, has
no clean url: the resource silently keeps its default url. Three checks warn
the administrator:

- when the config is saved, the number of identifiers that don’t match the
  pattern is displayed, with an example and the characters to add to it. This
  is the main point to watch: changing a pattern breaks all the existing clean
  urls at once.
- when an item set, an item or a media is saved in the admin interface, its
  own identifier is checked.
- the task "Check identifiers", available in the tab "Tasks" of the config
  form and in the tasks of the module [Easy Admin], lists all of them in a
  tabular file saved in the directory `files/cleanurl`. The report contains
  the resource ids, so the resources can be selected for a bulk edit.

Only the first literal value of the configured property is checked for each
resource, since it is the one used to build the url.

Nothing is fixed automatically: an identifier is a metadata with an external
meaning (ark, shelf mark), so changing it would break the references used
elsewhere. There are two ways to fix the identifiers without clean url: widen
the pattern, that is generally the right way, or normalize the values with the
module [Bulk Edit], that replaces a string or a regex in a property.

### Config for Ark

The module [Ark] allows to create normalized unique identifiers formatted like
`ark:/12025/b6KN`, where the "12025" is the NAAN (Name Assigning Authority
Number), that identifies the institution. It is assigned for free by the [ARK Alliance]
to any stable memory organization, through the [NAAN request form], that is used
to update an existing entry of the registry too. The registry is maintained at
the California Digital Library and mirrored at the National Library of Medicine
and the National Library of France. The "b6KN" is the short hash of the id, with
a control key. The name is always short, because four characters are enough to
create more than ten millions of unique names.

There are multiple way to config arks:

- With a prefix:
  - Identifier prefix: `ark:/12345/`.
  - Identifier are case sensitive: set true if you choose a format with a full
    alphabet (uppercase and lowercase letters).
  - Item:
    - Path: `ark:/12345/{item_identifier_short}`.
    - Pattern: `[a-zA-Z][a-zA-Z0-9]*`(or something else)
  - Media: `ark:/12345/{item_identifier_short}/{media_id}`.
- Without a prefix:
  - Identifier are case sensitive: set true if you choose a format with a full
    alphabet (uppercase and lowercase letters).
  - Don't escape the slash `/`.
  - Item:
    - Path: `{item_identifier}`.
    - Pattern: `[a-zA-Z][a-zA-Z0-9:/]*`(or something else, but with `:` and `/`)
  - Media: `{item_identifier}/{media_id}`.

Other options are at your convenience.

### Config for Omeka Classic compatibility

If you upgraded from Omeka Classic and you want to keep a redirection from your
current urls:

- skip main slug: `true`
- item set path: `collections/show/{item_set_id}`.
- item path: `items/show/{item_id}`.
- media path: `files/show/{media_id}`.


TODO
----

- [ ] Manage hierarchy of pages (/my-site/part-1/part-1.1/part-1.1.1).
- [ ] Forward/Redirect to the canonical url
- [ ] Support public clean urls for concepts (module [Thesaurus], (see `site_parts` in `Module.php`).
- [ ] Support item-parent schema for digital objects (`document/{item_identifier}/{digital_object_id}`): a digital object is a top-level resource without any item parent, so only the standalone schema is available for now.
- [x] Replace the check with/without space by a job that cleans all identifiers (see Bulk Check).
- [ ] Remove the management of the space to get resources from identifiers with a prefix.
- [ ] Improve speed to create url, in particular when creating urls in bulk (module Mapping). Create a table? Or even a single setting with the full list id/identifier? A job may already skip the clean urls when they are not displayed (see above), but the urls that are really displayed in bulk still need it.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Daniel Berthereau, 2012-2026 (see [Daniel-KM] on GitLab)
* Copyright BibLibre, 2016-2017

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech]. The upgrade for
Omeka S was built by [BibLibre] for [Paris Sciences et Lettres (PSL)]. Then, the
module was rewritten to manage various requirements.


[Clean Url]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl
[CleanUrl.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/releases
[Omeka S]: https://omeka.org/s
[Clean Url plugin]: https://gitlab.com/Daniel-KM/Omeka-plugin-CleanUrl
[Omeka]: https://omeka.org/classic
[BibLibre]: https://github.com/biblibre
[Ark]: https://gitlab.com/Daniel-KM/Omeka-S-module-Ark
[ARK Alliance]: https://arks.org
[NAAN request form]: https://docs.google.com/forms/d/e/1FAIpQLSf_847hNXtLGikR-XeDy1uT1AKd24DpHnt5UQh2i8ORRu7u-w/viewform
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[CleanUrl.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/releases
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[omeka/omeka-s#870]: https://github.com/omeka/omeka-s/issues/870
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Bulk Check]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkCheck
[Bulk Edit]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkEdit
[Digital Object]: https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject
[Cool URIs]: https://www.w3.org/TR/cooluris/ "Cool URIs for the Semantic Web"
[Easy Admin]: https://gitlab.com/Daniel-KM/Omeka-S-module-EasyAdmin
[Thesaurus]: https://gitlab.com/Daniel-KM/Omeka-S-module-Thesaurus
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
[Paris Sciences et Lettres (PSL)]: https://bibnum.explore.univ-psl.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
