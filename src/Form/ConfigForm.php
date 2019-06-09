<?php
namespace CleanUrl\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $this->add([
            'type' => Fieldset::class,
            'name' => 'clean_url_identifiers',
            'options' => [
                'label' => 'Identifiers', // @translate
            ],
        ]);
        $identifiersFieldset = $this->get('clean_url_identifiers');

        $identifiersFieldset->add([
            'name' => 'cleanurl_identifier_property',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Field where identifier is saved', // @translate
                'info' => $this->translate('Field where to save the identifier of the item or media.') // @translate
                    . ' ' . $this->translate('It should be an identifier used for all resource types (Item set, Item and Media).') // @translate
                    . ' ' . $this->translate('Default is to use "dcterms:identifier".'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_identifier_property',
                'required' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $identifiersFieldset->add([
            'name' => 'cleanurl_identifier_prefix',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Prefix of identifiers to use', // @translate
                'info' => $this->translate('Urls are built with the sanitized Dublin Core identifier with the selected prefix, for example "item:", "record:" or "doc =". Let empty to use simply the first identifier.') // @translate
                    . ' ' . $this->translate('If this identifier does not exists, the Omeka item id will be used.'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_identifier_prefix',
            ],
        ]);

        $identifiersFieldset->add([
            'name' => 'cleanurl_identifier_unspace',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Check the prefix without space', // @translate
                'info' => $this->translate('If checked, the prefix will be checked without space inside it too.') // @translate
                    . ' ' . $this->translate('This may be useful if the prefix is like "record =", but some records use "record=".'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_identifier_unspace',
            ],
        ]);

        $identifiersFieldset->add([
            'name' => 'cleanurl_case_insensitive',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Allow case insensitive identifier', // @translate
                'info' => $this->translate('If checked, all items will be available via an insensitive url too. This option is generally useless, because searches in database are generally case insensitive by default.') // @translate
                    . ' ' . $this->translate('Furthermore, it can slow server responses, unless you add an index for lower texts.'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_case_insensitive',
            ],
        ]);

        $this->add([
            'type' => Fieldset::class,
            'name' => 'clean_url_main_path',
            'options' => [
                'label' => 'Main base path', // @translate
            ],
        ]);
        $mainPathFieldset = $this->get('clean_url_main_path');

        $mainPathFieldset->add([
            'name' => 'cleanurl_main_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Main path to add', // @translate
                'info' => $this->translate('The main path to add in the beginning of the url, for example "library/" or "archives/".') // @translate
                    . ' ' . $this->translate('Let empty if you do not want any.'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_main_path',
            ],
        ]);

        $this->add([
            'type' => Fieldset::class,
            'name' => 'clean_url_item_sets',
            'options' => [
                'label' => 'Item sets', // @translate
            ],
        ]);
        $itemSetsFieldset = $this->get('clean_url_item_sets');

        $itemSetsFieldset->add([
            'name' => 'cleanurl_item_set_generic',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Generic name to add before item set identifier', // @translate
                'info' => $this->translate('This main path is added before the item set name, for example "/ my_item_sets / item set identifier".') // @translate
                . ' ' . $this->translate('Let empty if you do not want any, so path will be "/ item set identifier".'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_item_set_generic',
            ],
        ]);

        $this->add([
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

        $itemsFieldset->add([
            'name' => 'cleanurl_item_default',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Default url of items', // @translate
                'info' => 'Select the default format of the url for items.', // @translate
                'value_options' => [
                    'generic' => '/ generic / item identifier', // @translate
                    'item_set' => '/ item set identifier / item identifier', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cleanurl_item_default',
                'required' => true,
            ],
        ]);

        $itemsFieldset->add([
            'name' => 'cleanurl_item_allowed',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Allowed urls for items', // @translate
                'info' => $this->translate('Select the allowed formats for urls of items.') // @translate
                . ' ' . $this->translate('This is useful to allow a permalink and a search engine optimized link.'), // @translate
                'value_options' => [
                    'generic' => '/ generic / item identifier', // @translate
                    'item_set' => '/ item set identifier / item identifier', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cleanurl_item_allowed',
            ],
        ]);

        $itemsFieldset->add([
            'name' => 'cleanurl_item_generic',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Generic name to add before item identifier', // @translate
                'info' => 'The generic name to use if generic identifier is selected above, for example "item/", "record/" or "doc/".', // @translate
            ],
            'attributes' => [
                'id' => '',
            ],
        ]);

        $this->add([
            'type' => Fieldset::class,
            'name' => 'clean_url_medias',
            'options' => [
                'label' => 'Medias', // @translate
            ],
        ]);
        $mediaFieldset = $this->get('clean_url_medias');

        $mediaFieldset->add([
            'name' => 'cleanurl_media_default',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Default url of medias', // @translate
                'info' => 'Select the default format of the url for medias.', // @translate
                'value_options' => [
                    'generic' => '/ generic / media identifier', // @translate
                    'generic_item' => '/ generic / item identifier / media identifier', // @translate
                    'item_set' => '/ item_set identifier / media identifier', // @translate
                    'item_set_item' => '/ item set identifier / item identifier / media identifier', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cleanurl_media_default',
                'required' => true,
            ],
        ]);

        $mediaFieldset->add([
            'name' => 'cleanurl_media_allowed',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Allowed urls for medias', // @translate
                'info' => $this->translate('Select the allowed formats for urls of files.') // @translate
                    . ' ' . $this->translate('This is useful to allow a permalink and a search engine optimized link.'), // @translate
                'value_options' => [
                    'generic' => '/ generic / media identifier', // @translate
                    'generic_item' => '/ generic / item identifier / media identifier', // @translate
                    'item_set' => '/ item_set identifier / media identifier', // @translate
                    'item_set_item' => '/ item set identifier / item identifier / media identifier', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cleanurl_media_allowed',
            ],
        ]);

        $mediaFieldset->add([
            'name' => 'cleanurl_media_generic',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Generic name to add before item identifier', // @translate
                'info' => $this->translate('The generic name to use if generic identifier is selected above, for example "file/", "record/" or "image/".') // @translate
                    . ' ' . $this->translate('In the first case, currently, it should be different from the name used for items.'), // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_media_generic',
            ],
        ]);

        $this->add([
            'type' => Fieldset::class,
            'name' => 'clean_url_admin',
            'options' => [
                'label' => 'Admin Interface', // @translate
            ],
        ]);
        $adminFieldset = $this->get('clean_url_admin');

        $adminFieldset->add([
            'name' => 'cleanurl_use_admin',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Use in admin board', // @translate
                'info' => 'If checked, the clean url will be used in the admin board.', // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_use_admin',
            ],
        ]);

        $adminFieldset->add([
            'name' => 'cleanurl_display_admin_show_identifier',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Display identifier in admin resources', // @translate
                'info' => 'If checked, the identifier of each item will be displayed in the admin item show page.', // @translate
            ],
            'attributes' => [
                'id' => 'cleanurl_display_admin_show_identifier',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $identifiersFilter = $inputFilter->get('clean_url_identifiers');
        $identifiersFilter->add([
            'name' => 'cleanurl_identifier_property',
            'required' => true,
        ]);
        $identifiersFilter->add([
            'name' => 'cleanurl_identifier_prefix',
            'required' => false,
        ]);
        $identifiersFilter->add([
            'name' => 'cleanurl_identifier_unspace',
            'required' => false,
        ]);
        $identifiersFilter->add([
            'name' => 'cleanurl_case_insensitive',
            'required' => false,
        ]);

        $mainPathFilter = $inputFilter->get('clean_url_main_path');
        $mainPathFilter->add([
            'name' => 'cleanurl_main_path',
            'required' => false,
        ]);

        $itemSetsFilter = $inputFilter->get('clean_url_item_sets');
        $itemSetsFilter->add([
            'name' => 'cleanurl_item_set_generic',
            'required' => false,
        ]);

        $itemsFilter = $inputFilter->get('clean_url_items');
        $itemsFilter->add([
            'name' => 'cleanurl_item_default',
            'required' => true,
        ]);
        $itemsFilter->add([
            'name' => 'cleanurl_item_allowed',
            'required' => false,
        ]);
        $itemsFilter->add([
            'name' => 'cleanurl_item_generic',
            'required' => false,
        ]);

        $mediaFilter = $inputFilter->get('clean_url_medias');
        $mediaFilter->add([
            'name' => 'cleanurl_media_default',
            'required' => true,
        ]);
        $mediaFilter->add([
            'name' => 'cleanurl_media_allowed',
            'required' => false,
        ]);
        $mediaFilter->add([
            'name' => 'cleanurl_media_generic',
            'required' => false,
        ]);

        $adminFilter = $inputFilter->get('clean_url_admin');
        $adminFilter->add([
            'name' => 'cleanurl_display_admin_show_identifier',
            'required' => false,
        ]);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
