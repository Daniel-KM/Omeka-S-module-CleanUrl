Clean Url (module for Omeka S)
==============================

[![Build Status](https://travis-ci.org/biblibre/omeka-s-module-CleanUrl.svg?branch=master)](https://travis-ci.org/biblibre/omeka-s-module-CleanUrl)

[Clean Url] is a module for [Omeka S] that creates clean, readable and search
engine optimized URLs like `https://example.com/my_item_set/dc:identifier`
instead of `https://example.com/item/internal_code`. Used identifiers come from
standard Dublin Core metadata, or from a specific field, so they are easy to
manage.

This [Omeka S] module is a rewrite of the [Clean Url plugin] for [Omeka] by
[BibLibre] and intends to provide the same features as the original plugin.


Installation
------------

Uncompress files and rename module folder "CleanUrl".

Then install it like any other Omeka module and follow the config instructions.


Usage
-----

Clean urls are automatically displayed in public theme and they are not used in
the admin theme. They are case insensitive.

This module may be used with the module [Archive Repertory] to set similar paths
for real files (item_set_identifier / item_identifier / true_filename).

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

    - / :identifier_item (currently not available)
    - / generic_item / :identifier_item
    - / :identifier_item_set / :identifier_item
    - / generic_item_set / :identifier_item_set / :identifier_item
    - / main_path / :identifier_item
    - / main_path / generic_item / :identifier_item
    - / main_path / :identifier_item_set / :identifier_item
    - / main_path / generic_item_set / :identifier_item_set / :identifier_item

#### Medias

    - / :identifier_file (currently not available)
    - / :identifier_item / :identifier_file (currently not available)
    - / generic_file / :identifier_file
    - / generic_file / :identifier_item / :identifier_file
    - / :identifier_item_set / :identifier_file
    - / generic_item_set / :identifier_item_set / :identifier_file
    - / :identifier_item_set / :identifier_item / :identifier_file
    - / generic_item_set / :identifier_item_set / :identifier_item / :identifier_file
    - / main_path / :identifier_file
    - / main_path / :identifier_item / :identifier_file
    - / main_path / generic_file / :identifier_file
    - / main_path / generic_file / :identifier_item / :identifier_file
    - / main_path / :identifier_item_set / :identifier_file
    - / main_path / generic_item_set / :identifier_item_set / :identifier_file
    - / main_path / :identifier_item_set / :identifier_item / :identifier_file
    - / main_path / generic_item_set / :identifier_item_set / :identifier_item / :identifier_file

Note: only logical combinations of some of these paths are available together!


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


Contact
-------

Current maintainers:

* BibLibre
* Daniel Berthereau (see [Daniel-KM] on GitHub)

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech]. The upgrade for
Omeka S has been built for [Paris Sciences et Lettres (PSL)].


Copyright
---------

* Copyright Daniel Berthereau, 2012-2017
* Copyright BibLibre, 2016-2017


[Clean Url]: https://github.com/biblibre/omeka-s-module-CleanUrl
[Omeka S]: https://github.com/omeka/omeka-s
[Clean Url plugin]: https://github.com/Daniel-KM/CleanUrl
[Omeka]: https://omeka.org
[BibLibre]: https://github.com/biblibre
[module issues]: https://github.com/biblibre/omeka-s-module-CleanUrl/issues
[Archive Repertory]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
[Paris Sciences et Lettres (PSL)]: https://bibnum.explore.univ-psl.fr
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
