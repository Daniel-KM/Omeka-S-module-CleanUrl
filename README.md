Clean Url (module for Omeka S)
==============================

[Clean Url] is a module for [Omeka S] that creates clean, readable and search
engine optimized URLs like `https://example.com/my_item_set/dc:identifier`
instead of `https://example.com/item/internal_code`. Used identifiers come from
standard Dublin Core metadata, or from any specific field, so they are easy to
manage. It supports [Ark] and short urls too.

Furthermore, it makes possible to use a main site and additional sites, like in
Omeka Classic, so the main site won’t start with "/s/site-slug". The slug "/page/"
can be removed too.

This [Omeka S] module was initially based on a rewrite of the [Clean Url plugin]
for [Omeka] and provide the same features as the original plugin.


Installation
------------

This module uses the optional module [Generic], that may be installed first.

See general end user documentation for [installing a module].

Uncompress files and rename module folder `CleanUrl`.

Then install it like any other Omeka module and follow the config instructions.

IMPORTANT:
The module copies two files in the main config directory of Omeka:
- "clean_url.config.php": this is a list of all reserved words for the first
  level of the url, when there are no site and page prefixes. All common routes
  are included. It is larger than needed in order to manage future modules or
  improvments, according to existing modules in Omeka classic or Omeka S or
  common wishes. It can be edited as needed if you have a public route for a
  specific module.
- "clean_url.dynamic.php": this file is automatically updated to save the list
  of site slugs and some other settings in order to manage routing quickly, in
  particular when there are no site and page paths. You should not modify it
  manually.


Usage
-----

Clean urls are automatically displayed in public theme and they are not used in
the admin theme. They are case insensitive.

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


### Identifiers ###

Simply set an identifier for each record in a field. The recommended field is
`Dublin Core:Identifier`.

- Identifiers can be any strings with any characters, as long as they don’t
contain reserved characters like "/" and "%".
- To use numbers as identifier is possible but not recommended. if so, it’s
recommended that all records have got an identifier.
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

### Structure of urls ###

The configuration page let you choose the structure of paths for item sets,
items and files.

- A main path can be added , as "archives" or "library": `https://example.com/main_path/my_item_set/dc:identifier`.
- A generic and persistent part can be added for item sets, items and files,
for example `https://example.com/my_collections/item_set_identifier`, or `https://example.com/document/item_identifier`.
- Multiple urls can be set, in particular to have a permalink and a search
engine optimized link.
- If multiple structures of urls are selected, a default one will be used to set
the default url. Other ones can be used to get records.

So the configuration of the module let you choose among these possible paths:

#### Item sets

    - / :identifier_item_set
    - / generic_item_set / :identifier_item_set
    - / main_path / :identifier_item_set
    - / main_path / generic_item_set / :identifier_item_set

#### Items

    - / :identifier_item
    - / generic_item / :identifier_item
    - / :identifier_item_set / :identifier_item
    - / generic_item_set / :identifier_item_set / :identifier_item
    - / main_path / :identifier_item
    - / main_path / generic_item / :identifier_item
    - / main_path / :identifier_item_set / :identifier_item
    - / main_path / generic_item_set / :identifier_item_set / :identifier_item

#### Medias

    - / :identifier_media
    - / :identifier_item / :identifier_media
    - / generic_file / :identifier_media
    - / generic_file / :identifier_item / :identifier_media
    - / :identifier_item_set / :identifier_media
    - / generic_item_set / :identifier_item_set / :identifier_media
    - / :identifier_item_set / :identifier_item / :identifier_media
    - / generic_item_set / :identifier_item_set / :identifier_item / :identifier_media
    - / main_path / :identifier_media
    - / main_path / :identifier_item / :identifier_media
    - / main_path / generic_file / :identifier_media
    - / main_path / generic_file / :identifier_item / :identifier_media
    - / main_path / :identifier_item_set / :identifier_media
    - / main_path / generic_item_set / :identifier_item_set / :identifier_media
    - / main_path / :identifier_item_set / :identifier_item / :identifier_media
    - / main_path / generic_item_set / :identifier_item_set / :identifier_item / :identifier_media

Note: only logical combinations of some of these paths are available together!

A second and third main path can be added, for example to manage ark identifier:
main path is "ark:/" and the second main path is the naan.

The identifier of the media can be the position. In that case, the string for
this position can be formatted with function "sprintf". It is recommended to use
a format with a leading letter to avoid confusion with numeric media id.
Furthermore, the position may not be stable: a scanned image may be missing.
Finally, if the first media is not marked "1" in the database, use module [Bulk Check]
to fix them.

### Config for Ark

The module [Ark] allows to create normalized unique identifiers formatted like
`ark:/12025/b6KN`, where the "12025" is the id of the institution, that is
assigned for free by the [California Digital Library] to any institution with
historical or archival purposes. The "b6KN" is the short hash of the id, with a
control key. The name is always short, because four characters are enough to
create more than ten millions of unique names.

To config it, use these params:
- Resource identifiers: `prefix = ark:/12345/`.
- Identifier are case sensitive: set true if you choose a format with a full
  alphabet (uppercase and lowercase letters).
- Main base path: `main path = ark:/` and `sub-main path = 12345/`; if another
  main path is added, set them as sub-main path and sub-sub-main path path.
- Content: `default url = / generic / item identifier`, no generic path,
  `identifier includes item set identifie = no`.
- Media: : `default url = / generic / media identifier`, no generic path,
  `keep raw identifier = true`, `identifier includes item identifier = yes` (or
  `maybe` is some arks are missing).
Other options are at your convenience.


TODO
----

- Manage hierarchy of pages (/my-site/part-1/part-1.1/part-1.1.1).
- Forward/Redirect to the canonical url
- Replace the check with/without space by a job that cleans all identifiers.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
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

* Copyright Daniel Berthereau, 2012-2020 (see [Daniel-KM] on GitHub)
* Copyright BibLibre, 2016-2017

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech]. The upgrade for
Omeka S was built by [BibLibre] for [Paris Sciences et Lettres (PSL)]. Then, the
module was rewritten to manage various requirements.


[Clean Url]: https://github.com/Daniel-KM/Omeka-S-module-CleanUrl
[Omeka S]: https://omeka.org/s
[Clean Url plugin]: https://github.com/Daniel-KM/Omeka-plugin-CleanUrl
[Omeka]: https://omeka.org/classic
[BibLibre]: https://github.com/biblibre
[Ark]: https://github.com/Daniel-KM/Omeka-S-module-Ark
[Generic]: https://github.com/Daniel-KM/Omeka-S-module-Generic
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[omeka/omeka-s#870]: https://github.com/omeka/omeka-s/issues/870
[config/clean_url.config.php]: https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/blob/master/config/clean_url.config.php#L9
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues
[Archive Repertory]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Bulk Check]: https://github.com/Daniel-KM/Omeka-S-module-BulkCheck
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
[Paris Sciences et Lettres (PSL)]: https://bibnum.explore.univ-psl.fr
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
