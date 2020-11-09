<?php declare(strict_types=1);

namespace CleanUrl\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element\ArrayTextarea;
use Omeka\Form\Element\PropertySelect;

class ConfigForm extends Form
{
    public function init(): void
    {
        // Pages.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_pages',
                'options' => [
                    'label' => 'Sites and pages', // @translate
                ],
            ])
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
            ])
        ;

        // Identifiers.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_identifiers',
                'options' => [
                    'label' => 'Resource identifiers', // @translate
                ],
            ])
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
                    'info' => 'This prefix allows to find one identifier when there are multiple values: "ark:", "record:", or "doc =". Include space if needed. Let empty to use the first identifier. If this identifier does not exists, the Omeka item id will be used.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_prefix',
                    'placeholder' => 'ark:/12345/'
                ],
            ])
            /*
            ->add([
                'name' => 'cleanurl_identifier_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Short part', // @translate
                    'info' => 'Indicate the fixed part of the identifier that should be removed to get the short identifier.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_short',
                    'placeholder' => 'ark:/12345/'
                ],
            ])
            */
            ->add([
                'name' => 'cleanurl_identifier_prefix_part_of',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'The prefix is part of the identifier', // @translate
                    'info' => 'This option is required to get the whole identifier when needed, for example with the IIIF server.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_identifier_prefix_part_of',
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
        ;

        // Item sets.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_item_sets',
                'options' => [
                    'label' => 'Item sets', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_paths',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Paths', // @translate
                    'info' => 'The values are an unquoted regex from root with "~" as enclosure, ordered from the first to check to the last.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_paths',
                    'rows' => 3,
                    'placeholder' => 'collection/{item_set_identifier}',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_default',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Short path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_short',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_pattern',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Pattern of an item set identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_pattern',
                    'placeholder' => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_set_pattern_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional pattern of an item set short identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_set_pattern',
                ],
            ])
        ;

        // Items.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_items',
                'options' => [
                    'label' => 'Items', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_paths',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Paths', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_paths',
                    'rows' => 3,
                    'placeholder' => 'collection/{item_set_identifier}/{item_identifier}
document/{item_identifier}
',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_default',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Short path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_short',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_pattern',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Pattern of an item identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_pattern',
                    'placeholder' => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
            ])
            ->add([
                'name' => 'cleanurl_item_pattern_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional pattern of an item short identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_item_pattern_short',
                ],
            ])
        ;

        // Medias.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_medias',
                'options' => [
                    'label' => 'Medias', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_paths',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Paths', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_paths',
                    'rows' => 3,
                    'placeholder' => 'collection/{item_set_identifier}/{item_identifier}/{media_id}
document/{item_identifier}/p{media_position}
',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_default',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Short path', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_short',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_pattern',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Pattern of a media identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_pattern',
                ],
            ])
            ->add([
                'name' => 'cleanurl_media_pattern_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional pattern of a media short identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_media_pattern_short',
                ],
            ])
        ;

        // Admin.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'clean_url_admin',
                'options' => [
                    'label' => 'Admin Interface', // @translate
                ],
            ])
            ->add([
                'name' => 'cleanurl_admin_use',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use in admin board', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_use',
                ],
            ])
            ->add([
                'name' => 'cleanurl_admin_reserved',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Other reserved routes in admin', // @translate
                    'info' => 'This option allows to fix routes for unmanaged modules. Add them in the file cleanurl.config.php or here, one by row.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_reserved',
                    'rows' => 3,
                ],
            ])
        ;
    }
}
