<?php
namespace CleanUrl\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_pages',
                'options' => [
                    'label' => 'Sites and pages', // @translate
                ],
            ]);
        $siteFieldset = $this->get('clean_url_pages');
        $siteFieldset
            ->add([
                'name' => 'cleanurl_site_skip_main',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Skip "s/site-slug/" for default site', // @translate
                    'info' => 'The main site is defined in the main settings.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_site_skip_main',
                ],
            ])
            ->add([
                'name' => 'cleanurl_site_slug',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Rename or skip prefix /s/', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_site_slug',
                    'placeholder' => 's/', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_page_slug',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Rename or skip prefix /page/', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_page_slug',
                    'placeholder' => 'page/', // @translate
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_identifiers',
                'options' => [
                    'label' => 'Resource identifiers', // @translate
                ],
            ]);
        $identifiersFieldset = $this->get('clean_url_identifiers');
        $identifiersFieldset
            ->add([
                'name' => 'cleanurl_identifier_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property of identifier', // @translate
                    'info' => 'Field where the identifier of the resource is set. Default is "dcterms:identifier".', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_property',
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_identifier_prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Prefix to select an identifier', // @translate
                    'info' => 'This prefix allows to find one identifier when there are multiple values: "ark:", "record:", or "doc =". Let empty to use the first identifier. If this identifier does not exists, the Omeka item id will be used.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_prefix',
                ],
            ])
            ->add([
                'name' => 'cleanurl_identifier_unspace',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Check the prefix without space', // @translate
                    'info' => 'This option is used for not homogeneous value and allow to check values without space inside, for example the prefix is "doc:", but some records use "doc :".', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_unspace',
                ],
            ])
            ->add([
                'name' => 'cleanurl_identifier_case_sensitive',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Identifiers are case sensitive', // @translate
                    'info' => 'Some formats of short identifiers are case sensitive, so search will be done in a binary way.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_case_sensitive',
                ],
            ])
            ->add([
                'name' => 'cleanurl_identifier_prefix_part_of',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'The prefix is part of the identifier (like ark)', // @translate
                    'info' => 'This option is required to get the whole identifier when needed, for example with the IIIF server.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_prefix_part_of',
                ],
            ])
            ->add([
                'name' => 'cleanurl_identifier_undefined',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'When no identifier exists', // @translate
                    'value_options' => [
                        'default' => 'Omeka route: / resource type / id', // @translate
                        'main_generic' => 'Main and generic path: / main / generic / id', // @translate
                        'generic' => 'Generic path: / generic / id', // @translate
                        'exception' => 'Error 404 (except in admin)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_undefined',
                    'required' => true,
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_main_path',
                'options' => [
                    'label' => 'Main base path', // @translate
                ],
            ]);
        $mainPathFieldset = $this->get('clean_url_main_path');
        $mainPathFieldset
            ->add([
                'name' => 'cleanurl_main_path',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Main path for resources', // @translate
                    'info' => 'The main path to add in the beginning of the url for resources, for example "library/", or "archives/". Let empty if you do not want any.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_main_path',
                ],
            ])
            ->add([
                'name' => 'cleanurl_main_path_2',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Sub-main path for resources', // @translate
                    'info' => 'A second path to add in the beginning of the url for resources, for example "ark:/". Let empty if you do not want any.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_main_path_2',
                ],
            ])
            ->add([
                'name' => 'cleanurl_main_path_3',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Sub-sub-main path for resources', // @translate
                    'info' => 'A third path to add in the beginning of the url for resources, for example the ark naan. Let empty if you do not want any.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_main_path_3',
                ],
            ])
            ->add([
                'name' => 'cleanurl_main_short',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Allow short url', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'main' => 'Without main path', // @translate
                        'main_sub' => 'Without main and sub-path', // @translate
                        'main_sub_sub' => 'Without main, sub-path and sub-sub-path', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_main_short',
                    'required' => true,
                    'value' => 'none',
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_item_sets',
                'options' => [
                    'label' => 'Item sets', // @translate
                ],
            ]);
        $itemSetsFieldset = $this->get('clean_url_item_sets');
        $itemSetsFieldset
            ->add([
                'name' => 'cleanurl_item_set_generic',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Generic name to add before item set identifier', // @translate
                    'info' => 'Allow to set an url for item sets like "/ my_item_sets / item set identifier".', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_generic',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_keep_raw',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Keep raw identifier (no url encode)', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_keep_raw',
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_items',
                'options' => [
                    'label' => 'Items', // @translate
                ],
                'attributes' => [
                    'id' => '',
                ],
            ]);
        $itemsFieldset = $this->get('clean_url_items');
        $itemsFieldset
            ->add([
                'name' => 'cleanurl_item_default',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default url of items', // @translate
                    'info' => 'Select the default format of the url for items.', // @translate
                    'value_options' => [
                        'generic_item' => '/ generic / item identifier', // @translate
                        'generic_item_full' => '/ generic / full item identifier', // @translate
                        'item_set_item' => '/ item set identifier / item identifier', // @translate
                        'item_set_item_full' => '/ item set identifier / full item identifier', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_default',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_allowed',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Allowed urls for items', // @translate
                    'info' => 'Select the allowed formats for urls of items, for example to allow a permalink and a seo link.', // @translate
                    'value_options' => [
                        'generic_item' => '/ generic / item identifier', // @translate
                        'generic_item_full' => '/ generic / full item identifier', // @translate
                        'item_set_item' => '/ item set identifier / item identifier', // @translate
                        'item_set_item_full' => '/ item set identifier / full item identifier', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_allowed',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_generic',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Generic name to add before item identifier', // @translate
                    'info' => 'The prefix to use for items, for example "item/", "record/" or "doc/".', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_generic',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_keep_raw',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Keep raw identifier (no url encode)', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_keep_raw',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_item_set_included',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'The identifier includes the item set identifier', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'maybe' => 'Maybe', // @translate
                        'yes' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_item_set_included',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_item_set_undefined',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'When item set is undefined', // @translate
                    'value_options' => [
                        'parent_id' => 'Use item set id', // @translate
                        'undefined' => 'Use main setting for undefined', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_item_set_undefined',
                    'required' => true,
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_medias',
                'options' => [
                    'label' => 'Medias', // @translate
                ],
            ]);
        $mediaFieldset = $this->get('clean_url_medias');
        $mediaFieldset
            ->add([
                'name' => 'cleanurl_media_default',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default url of medias', // @translate
                    'info' => 'Select the default format of the url for medias.', // @translate
                    'value_options' => [
                        'generic_media' => '/ generic / media identifier', // @translate
                        'generic_media_full' => '/ generic / full media identifier', // @translate
                        'generic_item_media' => '/ generic / item identifier / media identifier', // @translate
                        'generic_item_full_media' => '/ generic / full item identifier / media identifier', // @translate
                        'generic_item_media_full' => '/ generic / item identifier / full media identifier', // @translate
                        'generic_item_full_media_full' => '/ generic / full item identifier / full media identifier', // @translate
                        'item_set_media' => '/ item_set identifier / media identifier', // @translate
                        'item_set_media_full' => '/ item_set identifier / full media identifier', // @translate
                        'item_set_item_media' => '/ item set identifier / item identifier / media identifier', // @translate
                        'item_set_item_full_media' => '/ item set identifier / full item identifier / media identifier', // @translate
                        'item_set_item_media_full' => '/ item set identifier / item identifier / full media identifier', // @translate
                        'item_set_item_full_media_full' => '/ item set identifier / full item identifier / full media identifier', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_default',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_allowed',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Allowed urls for medias', // @translate
                    'info' => 'Select the allowed formats for urls of medias, for example to allow a permalink and a seo link.', // @translate
                    'value_options' => [
                        'generic_media' => '/ generic / media identifier', // @translate
                        'generic_media_full' => '/ generic / full media identifier', // @translate
                        'generic_item_media' => '/ generic / item identifier / media identifier', // @translate
                        'generic_item_full_media' => '/ generic / full item identifier / media identifier', // @translate
                        'generic_item_media_full' => '/ generic / item identifier / full media identifier', // @translate
                        'generic_item_full_media_full' => '/ generic / full item identifier / full media identifier', // @translate
                        'item_set_media' => '/ item_set identifier / media identifier', // @translate
                        'item_set_media_full' => '/ item_set identifier / full media identifier', // @translate
                        'item_set_item_media' => '/ item set identifier / item identifier / media identifier', // @translate
                        'item_set_item_full_media' => '/ item set identifier / full item identifier / media identifier', // @translate
                        'item_set_item_media_full' => '/ item set identifier / item identifier / full media identifier', // @translate
                        'item_set_item_full_media_full' => '/ item set identifier / full item identifier / full media identifier', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_allowed',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_generic',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Generic name to add before item identifier', // @translate
                    'info' => 'The prefix to use for medias, for example "file/", "record/" or "image/". in some cases, it shoud be different from the name used for items.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_generic',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_keep_raw',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Keep raw identifier (no url encode)', // @translate
                    'info' => 'This option may be needed when the resource contains the parent identifier separated with a "/", like ark identifiers.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_keep_raw',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_item_set_included',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'The identifier includes the item set identifier', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'maybe' => 'Maybe', // @translate
                        'yes' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_item_set_included',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_item_included',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'The identifier includes the item identifier', // @translate
                    'info' => 'This option may be needed when the resource contains the parent identifier, like ark media identifiers.', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'maybe' => 'Maybe', // @translate
                        'yes' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_item_included',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_item_set_undefined',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'When item set is undefined', // @translate
                    'value_options' => [
                        'parent_id' => 'Use item set id', // @translate
                        'undefined' => 'Use main undefined setting', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_item_set_undefined',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_item_undefined',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'When item identifier is undefined', // @translate
                    'value_options' => [
                        'parent_id' => 'Use item id', // @translate
                        'undefined' => 'Use main undefined setting', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_item_undefined',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_media_undefined',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'When media identifier is undefined', // @translate
                    'value_options' => [
                        'id' => 'Use media id', // @translate
                        'position' => 'Use position with format below', // @translate
                        'undefined' => 'Use main undefined setting', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_media_undefined',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_format_position',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Format of the media position', // @translate
                    'info' => 'A "sprintf" string that will format the position. It is recommended to use a format with a leading letter to avoid confusion with numeric media id. Furthermore, the position may not be stable: a scanned image may be missing. Finally, if the first media is not marked "1" in the database, use module "Bulk Check" to fix positions.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_format_position',
                    'placeholder' => 'p%d',
                ],
            ]);

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_admin',
                'options' => [
                    'label' => 'Admin Interface', // @translate
                ],
            ]);
        $adminFieldset = $this->get('clean_url_admin');
        $adminFieldset
            ->add([
                'name' => 'cleanurl_admin_use',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use in admin board', // @translate
                    'info' => 'If checked, the clean url will be used in the admin board.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_use',
                ],
            ])
            ->add([
                'name' => 'cleanurl_admin_show_identifier',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display identifier in admin resources', // @translate
                    'info' => 'If checked, the identifier of each item will be displayed in the admin item show page.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_show_identifier',
                ],
            ])
            ->add([
                'name' => 'cleanurl_admin_reserved',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Other reserved routes in admin', // @translate
                    'info' => 'This option allows to fix routes for unmanaged modules. Add them in the file cleanurl.config.php or here, one by row.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_reserved',
                    'rows' => 5,
                ],
            ]);

        $inputFilter = $this->getInputFilter();
        $siteFilter = $inputFilter->get('clean_url_pages');
        $siteFilter
            ->add([
                'name' => 'cleanurl_site_skip_main',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_site_slug',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_page_slug',
                'required' => false,
            ]);

        $identifiersFilter = $inputFilter->get('clean_url_identifiers');
        $identifiersFilter
            ->add([
                'name' => 'cleanurl_identifier_property',
                'required' => true,
            ])
            ->add([
                'name' => 'cleanurl_identifier_prefix',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_identifier_unspace',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_identifier_case_sensitive',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_identifier_undefined',
                'required' => true,
            ]);

        $itemSetsFilter = $inputFilter->get('clean_url_main_path');
        $itemSetsFilter
            ->add([
                'name' => 'cleanurl_main_path',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_main_path_2',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_main_path_3',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_main_short',
                'required' => true,
            ]);

        $itemSetsFilter = $inputFilter->get('clean_url_item_sets');
        $itemSetsFilter
            ->add([
                'name' => 'cleanurl_item_set_generic',
                'required' => false,
            ]);

        $itemsFilter = $inputFilter->get('clean_url_items');
        $itemsFilter
            ->add([
                'name' => 'cleanurl_item_default',
                'required' => true,
            ])
            ->add([
                'name' => 'cleanurl_item_allowed',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_item_generic',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_item_item_set_included',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_item_item_set_undefined',
                'required' => true,
            ]);

        $mediaFilter = $inputFilter->get('clean_url_medias');
        $mediaFilter
            ->add([
                'name' => 'cleanurl_media_default',
                'required' => true,
            ])
            ->add([
                'name' => 'cleanurl_media_allowed',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_media_generic',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_media_item_set_included',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_media_item_included',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_media_item_set_undefined',
                'required' => true,
            ])
            ->add([
                'name' => 'cleanurl_media_item_undefined',
                'required' => true,
            ])
            ->add([
                'name' => 'cleanurl_media_media_undefined',
                'required' => true,
            ]);

        $adminFilter = $inputFilter->get('clean_url_admin');
        $adminFilter
            ->add([
                'name' => 'cleanurl_admin_use',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_admin_show_identifier',
                'required' => false,
            ])
            ->add([
                'name' => 'cleanurl_admin_reserved',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Get each line of a string separately.
     *
     * @param string $string
     * @return array
     */
    public function stringToList($string)
    {
        return is_array($string)
            ? $string
            : array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string))), 'strlen');
    }
}
