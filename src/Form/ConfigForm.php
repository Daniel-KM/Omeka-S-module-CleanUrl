<?php declare(strict_types=1);

namespace CleanUrl\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element\ArrayTextarea;
use Omeka\Form\Element\PropertySelect;

class ConfigForm extends Form
{
    /**
     * The settings are in the first tab and the processes in the second one.
     *
     * The ids are the ones used before the tabs were declared here, so the
     * anchors of the sections are unchanged.
     *
     * @see \Common\View\Helper\FormTabs
     */
    /**
     * The sections replace the fieldsets that were only titles, so the settings
     * keep their name and the elements stay at the first level of the form.
     */
    protected $elementGroups = [
        'pages' => 'Sites and pages', // @translate
        'resources' => 'Resources', // @translate
        'other' => 'Other options', // @translate
        'admin' => 'Admin Interface', // @translate
    ];

    protected $elementTabs = [
        'cleanurl-settings' => 'Settings', // @translate
        'cleanurl-tasks' => 'Tasks', // @translate
    ];

    public function init(): void
    {
        // Pages.

        $this
            ->setOption('element_groups', $this->elementGroups)
            ->setOption('element_tabs', $this->elementTabs)
            ->add([
                'name' => 'cleanurl_site_skip_main',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'pages',
                    'tab' => 'cleanurl-settings',
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
                    'element_group' => 'pages',
                    'tab' => 'cleanurl-settings',
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
                    'element_group' => 'pages',
                    'tab' => 'cleanurl-settings',
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
                    'element_group' => 'resources',
                    'tab' => 'cleanurl-settings',
                    'label' => 'Item sets', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_item_set', [
                'default_placeholder' => 'collection/{item_set_identifier}',
                'pattern_placeholder' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'prefix_placeholder' => 'ark:/12345/',
            ])
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_item',
                'options' => [
                    'element_group' => 'resources',
                    'tab' => 'cleanurl-settings',
                    'label' => 'Items', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_item', [
                'default_placeholder' => 'document/{item_identifier}',
                'pattern_placeholder' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'prefix_placeholder' => 'ark:/12345/',
            ])
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_media',
                'options' => [
                    'element_group' => 'resources',
                    'tab' => 'cleanurl-settings',
                    'label' => 'Medias', // @translate
                ],
            ])
            ->appendResourceFieldset('cleanurl_media', [
                'default_placeholder' => 'document/{item_identifier}/{media_id}',
                'pattern_placeholder' => '',
                'prefix_placeholder' => '',
            ])
        ;

        if (class_exists('DigitalObject\Module', false)) {
            $this
                ->add([
                    'type' => Fieldset::class,
                    'name' => 'cleanurl_digital_object',
                    'options' => [
                        'element_group' => 'resources',
                        'tab' => 'cleanurl-settings',
                        'label' => 'Digital objects', // @translate
                    ],
                ])
                ->appendResourceFieldset('cleanurl_digital_object', [
                    'default_placeholder' => 'digital-object/{digital_object_identifier}',
                    'pattern_placeholder' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                    'prefix_placeholder' => 'ark:/12345/',
                ])
            ;
        }

        // Generic.

        $this
            ->add([
                'name' => 'cleanurl_canonical',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'other',
                    'tab' => 'cleanurl-settings',
                    'label' => 'Add a canonical link to the clean url', // @translate
                    'info' => 'On public resource and page views, add a "canonical" link to the clean url so search engines do not index the original and clean urls as duplicate pages.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_canonical',
                ],
            ])
        ;

        // Admin.

        $this
            ->add([
                'name' => 'cleanurl_admin_use',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'admin',
                    'tab' => 'cleanurl-settings',
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
                    'element_group' => 'admin',
                    'tab' => 'cleanurl-settings',
                    'label' => 'Other reserved routes in admin', // @translate
                    'info' => 'This option allows to fix routes for unmanaged modules. Add them in the file cleanurl.config.php or here, one by row.', // @translate
                ],
                'attributes' => [
                    'id' => 'cleanurl_admin_reserved',
                    'rows' => 3,
                ],
            ])
        ;

        // Tasks.

        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'cleanurl_check',
                'options' => [
                    'tab' => 'cleanurl-tasks',
                    'label' => 'Check identifiers', // @translate
                ],
            ])
        ;

        $this
            ->get('cleanurl_check')
            ->add([
                'name' => 'check_note',
                'type' => CommonElement\Note::class,
                'options' => [
                    'text' => 'List the identifiers that have no clean url, because they don’t match the pattern or because they are a reserved word. The report is a tabular file saved in the directory "files/cleanurl", with the resource ids, so the resources can be selected for a bulk edit. Nothing is modified: an identifier is a metadata with an external meaning, so it is never fixed automatically. To fix them, either widen the pattern above, that is generally the right way, or normalize the values with the module Bulk Edit. Save the settings before running the task.', // @translate
                ],
            ])
            ->add([
                'name' => 'process_check',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Check identifiers', // @translate
                ],
                'attributes' => [
                    'id' => 'process_check',
                    'value' => 'Check identifiers', // @translate
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
