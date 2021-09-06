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

This module uses the optional module [Generic], that may be installed first.

See general end user documentation for [installing a module].

Uncompress files and rename module folder `CleanUrl`.

Then install it like any other Omeka module and follow the config instructions.

**IMPORTANT**:
The module copies one file in the main config directory of Omeka, "cleanurl.config.php".
this is a list of all reserved words for the first level of the url, when
there are no site and page prefixes. All common routes are included. It is
larger than needed in order to manage future modules or improvments, according
to existing modules in Omeka classic or Omeka S or common wishes.
Furthermore, it contains the list of site slugs and some other settings in order
to manage routing quickly, in particular when there are no site and page paths.
Contrary to a previous version, this file is automatically updated and should
not be updated manually.


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

### Config for Ark

The module [Ark] allows to create normalized unique identifiers formatted like
`ark:/12025/b6KN`, where the "12025" is the id of the institution, that is
assigned for free by the [California Digital Library] to any institution with
historical or archival purposes. The "b6KN" is the short hash of the id, with a
control key. The name is always short, because four characters are enough to
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
- [x] Replace the check with/without space by a job that cleans all identifiers (see Bulk Check).
- [ ] Remove the management of the space to get resources from identifiers with a prefix.


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

* Copyright Daniel Berthereau, 2012-2021 (see [Daniel-KM] on GitLab)
* Copyright BibLibre, 2016-2017

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech]. The upgrade for
Omeka S was built by [BibLibre] for [Paris Sciences et Lettres (PSL)]. Then, the
module was rewritten to manage various requirements.


[Clean Url]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl
[Omeka S]: https://omeka.org/s
[Clean Url plugin]: https://gitlab.com/Daniel-KM/Omeka-plugin-CleanUrl
[Omeka]: https://omeka.org/classic
[BibLibre]: https://github.com/biblibre
[Ark]: https://gitlab.com/Daniel-KM/Omeka-S-module-Ark
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[omeka/omeka-s#870]: https://github.com/omeka/omeka-s/issues/870
[config/clean_url.config.php]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/blob/master/config/clean_url.config.php#L9
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Bulk Check]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkCheck
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
[Paris Sciences et Lettres (PSL)]: https://bibnum.explore.univ-psl.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
