<?php

namespace VersionControl;

use ProcessWire\InputfieldWrapper,
    ProcessWire\Inputfield,
    ProcessWire\VersionControl,
    ProcessWire\ProcessVersionControl;

class ModuleConfig extends \ProcessWire\Wire {

    /**
     * Constructor method
     *
     * @param array $data Config data array.
     */
    public function __construct(array $data) {
        parent::__construct();
        $this->data = $data;
    }

    /**
     * Get all config fields for the module
     *
     * @return InputfieldWrapper InputfieldWrapper with module config inputfields.
     */
    public function getFields() {

        $fields = $this->wire(new InputfieldWrapper());
        $modules = $this->wire('modules');
        $data = $this->data;

        // merge default config settings (custom values overwrite defaults)
        $defaults = VersionControl::$defaultData;
        $data = array_merge($defaults, $data);

        // fieldset: general settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("General Settings");
        $fieldset->icon = "cogs";
        $fields->add($fieldset);

        // for which templates should we track values?
        $field = $modules->get("InputfieldAsmSelect");
        $field->name = "enabled_templates";
        $field->label = $this->_("Enable version control for these templates");
        $field->icon = "file-o";
        if (isset($data['enable_all_templates']) && $data['enable_all_templates']) {
            $field->collapsed = Inputfield::collapsedLocked;
            $field->notes = $this->_("This setting has no effect because version control is currently enabled for all templates via Advanced Settings.");
        } else {
            $field->notes = $this->_("Removing a template from here will also remove any data stored for it.");
        }
        foreach ($this->wire('templates')->getAll() as $key => $template) {
            if ($template->name != "language") $field->addOption($key, $template);
        }
        if (isset($data['enabled_templates'])) $field->value = $data['enabled_templates'];
        $fieldset->add($field);

        // for which fields should we track values?
        $field = $modules->get("InputfieldAsmSelect");
        $field->name = "enabled_fields";
        $field->label = $this->_("Enable version control for these fields");
        $field->notes = $this->_("Only fields of compatible fieldtypes can be selected. If no fields are selected, all fields of compatible fieldtypes are considered enabled. Removing a field from here will also remove any data stored for it.");
        $field->icon = "file-text-o";
        $types = implode($data['compatible_fieldtypes'], "|");
        $field->addOptions($this->wire('fields')->find("type=$types")->getArray());
        if (isset($data['enabled_fields'])) $field->value = $data['enabled_fields'];
        $fieldset->add($field);

        // display config options from the Process module
        if ($modules->isInstalled('ProcessVersionControl')) {
            $p = $modules->get('ProcessVersionControl');
            $p_data = $modules->getModuleConfigData('ProcessVersionControl');
            $p_fields = $this->wire(new ProcessModuleConfig($p_data))->getFields();
            foreach ($p_fields as $p_field) {
                $p_field->name .= "_p";
                if ($p_field->collapsed == Inputfield::collapsedHidden) {
                    $p_field->collapsed = Inputfield::collapsedNo;
                } else {
                    $p_field->collapsed = Inputfield::collapsedHidden;
                }
                if ($p_field instanceof InputfieldWrapper) {
                    foreach ($p_field as $p_subfield) {
                        $p_subfield->name .= "_p";
                        if ($p_subfield->showIf) {
                            $p_subfield->showIf = str_replace("=", "_p=", $p_subfield->showIf);
                        }
                        $p_field->add($p_subfield);
                    }
                }
            }
            $fields->add($p_fields);
        }

        // fieldset: cleanup settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("Cleanup Settings");
        $fieldset->icon = "trash-o";
        $fields->add($fieldset);

        // for how long should collected data be retained?
        if ($modules->isInstalled("LazyCron")) {
            $field = $modules->get("InputfieldSelect");
            $field->addOption('1 WEEK', $this->_('1 week'));
            $field->addOption('2 WEEK', $this->_('2 weeks'));
            $field->addOption('1 MONTH', $this->_('1 month'));
            $field->addOption('2 MONTH', $this->_('2 months'));
            $field->addOption('3 MONTH', $this->_('3 months'));
            $field->addOption('6 MONTH', $this->_('6 months'));
            $field->addOption('1 YEAR', $this->_('1 year'));
            $field->notes = $this->_("Leave empty to disable automatic time-based cleanup.");
            if (isset($data['data_max_age'])) $field->value = $data['data_max_age'];
        } else {
            $field = $modules->get("InputfieldMarkup");
            $field->description = $this->_("Automatic cleanup requires Lazy Cron module.");
        }
        $field->label = $this->_("For how long should we retain collected data?");
        $field->name = "data_max_age";
        $fieldset->add($field);

        // should we limit the amount of revisions saved for each field + page combination?
        $field = $modules->get("InputfieldSelect");
        $field->name = "data_row_limit";
        $field->label = $this->_("Revisions retained for each field + page combination");
        $field->addOptions([
            10 => '10',
            20 => '20',
            50 => '50',
            100 => '100',
        ]);
        $field->notes = $this->_("Leave empty to not impose limits for stored revisions.");
        if (isset($data['data_row_limit'])) $field->value = $data['data_row_limit'];
        $fieldset->add($field);

        // which cleanup methods (or features) should we enable?
        $field = $modules->get("InputfieldCheckboxes");
        $field->name = "cleanup_methods";
        $field->label = $this->_("Additional cleanup methods");
        $field->addOptions([
            'deleted_pages' => $this->_("Delete all stored data for deleted pages"),
            'deleted_fields' => $this->_("Delete all stored data for deleted fields"),
            'changed_template' => $this->_("Delete data stored for non-existing fields after template is changed"),
            'removed_fieldgroup_fields' => $this->_("Delete data stored for fields that are removed from page's fieldgroup"),
        ]);
        if (isset($data['cleanup_methods'])) $field->value = $data['cleanup_methods'];
        $fieldset->add($field);

        // fieldset: advanced settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("Advanced Settings");
        $fieldset->icon = "graduation-cap";
        $fieldset->collapsed = Inputfield::collapsedYes;
        $fields->add($fieldset);

        // define fieldtypes considered compatible with this module
        $field = $modules->get("InputfieldAsmSelect");
        $field->name = "compatible_fieldtypes";
        $field->label = $this->_("Compatible fieldtypes");
        $field->description = $this->_("Fieldtypes considered compatible with this module.");
        $field->icon = 'list-alt';
        $selectable_fieldtypes = $modules->find('className^=Fieldtype');
        foreach ($selectable_fieldtypes as $key => $fieldtype) {
            // remove native fieldtypes known to be incompatible
            if ($fieldtype == "FieldtypePassword" || strpos($fieldtype->name, "FieldtypeFieldset") === 0) {
                unset($selectable_fieldtypes[$key]);
            }
        }
        $field->addOptions($selectable_fieldtypes->getArray());
        $field->notes = $this->_("Please note that selecting any fieldtypes not selected by default may result in various problems.");
        if (isset($data['compatible_fieldtypes'])) $field->value = $data['compatible_fieldtypes'];
        $fieldset->add($field);

        // enable version control for *all* templates
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "enable_all_templates";
        $field->label = $this->_("Enable version control for all templates");
        $field->description = $this->_("If this option is selected, Version Control will track changes to all templates.");
        $field->notes = $this->_("This could result in very large amounts of collected data, and cause other unexpected side effects!");
        $field->attr('checked', isset($data['enable_all_templates']) && $data['enable_all_templates']);
        $fieldset->add($field);

        return $fields;
    }

}
