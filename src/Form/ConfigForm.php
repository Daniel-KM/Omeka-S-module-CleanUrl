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

        // Resources

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_item_set',
                'options' => [
                    'label' => 'Item sets', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_item_set', [
                'default_placeholder' => 'collection/{item_set_identifier}',
                'pattern_placeholder' => '[a-zA-Z0-9_-]+',
                'prefix_placeholder' => 'ark:/12345/',
            ])
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_item',
                'options' => [
                    'label' => 'Items', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_item', [
                'default_placeholder' => 'document/{item_identifier}',
                'pattern_placeholder' => '[a-zA-Z0-9_-]+',
                'prefix_placeholder' => 'ark:/12345/',
            ])
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_media',
                'options' => [
                    'label' => 'Medias', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_media', [
                'default_placeholder' => 'document/{item_identifier}/{media_id}',
                'pattern_placeholder' => '',
                'prefix_placeholder' => '',
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

    protected function appendResourceFieldset($fieldset, array $options): self
    {
        $this
            ->get($fieldset)
            ->add([
                'name' => 'default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default path', // @translate
                    'info' => 'Path with placeholders, without site slug.', // @translate
                ],
                'attributes' => [
                    'id' => 'default',
                    'placeholder' => $options['default_placeholder'],
                ],
            ])
            ->add([
                'name' => 'short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Short path', // @translate
                ],
                'attributes' => [
                    'id' => 'short',
                ],
            ])
            ->add([
                'name' => 'paths',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Additional paths', // @translate
                ],
                'attributes' => [
                    'id' => 'paths',
                    'rows' => 3,
                ],
            ])
            ->add([
                'name' => 'pattern',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Pattern of identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'pattern',
                    'placeholder' => $options['pattern_placeholder'],
                ],
            ])
            ->add([
                'name' => 'pattern_short',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional pattern for short identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'pattern_short',
                ],
            ])
            ->add([
                'name' => 'property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property for identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'property',
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Prefix to select an identifier', // @translate
                    'info' => 'This prefix allows to find one identifier when there are multiple values: "ark:", "record:", or "doc =". Include space if needed. Let empty to use the first identifier. If this identifier does not exists, the Omeka resource id will be used.', // @translate
                ],
                'attributes' => [
                    'id' => 'prefix',
                    'placeholder' => $options['prefix_placeholder'],
                ],
            ])
            ->add([
                'name' => 'prefix_part_of',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'The prefix is part of the identifier', // @translate
                ],
                'attributes' => [
                    'id' => 'prefix_part_of',
                ],
            ])
            ->add([
                'name' => 'keep_slash',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Identifiers have slash, so don’t escape it', // @translate
                ],
                'attributes' => [
                    'id' => 'keep_slash',
                ],
            ])
            ->add([
                'name' => 'case_sensitive',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Identifiers are case sensitive', // @translate
                ],
                'attributes' => [
                    'id' => 'case_sensitive',
                ],
            ])
        ;
        return $this;
    }
}
