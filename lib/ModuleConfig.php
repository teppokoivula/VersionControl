<?php

namespace VersionControl;

use ProcessWire\InputfieldWrapper;
use ProcessWire\Inputfield;
use ProcessWire\VersionControl;

/**
 * Version Control Config
 *
 * @version 1.2.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
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
        $types = implode("|", $data['compatible_fieldtypes']);
        $field->addOptions($this->wire('fields')->find("type=$types")->getArray());
        if (isset($data['enabled_fields'])) $field->value = $data['enabled_fields'];
        $fieldset->add($field);

        // enable version control in view only mode for locked fields?
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "enable_locked_fields";
        $field->label = $this->_("Enable version control for locked fields");
        $field->notes = $this->_("By default version control features are not displayed for locked (uneditable) fields. If this option is enabled, users will be able to view version history, but still won't be able to restore earlier revisions.");
        $field->icon = "unlock";
        $fieldset->add($field);
        $field->attr('checked', isset($data['enable_locked_fields']) && $data['enable_locked_fields']);

        // display config options from companion modules
        $ext_data = [
            'p' => ['ProcessVersionControl', 'VersionControl\ProcessModuleConfig'],
            'c' => ['VersionControlCleanup', 'VersionControl\CleanupModuleConfig'],
        ];
        foreach ($ext_data as $ext_key => $ext_module) {
            if (!$modules->isInstalled($ext_module[0])) continue;
            $e = $modules->get($ext_module[0]);
            $e_data = $modules->getModuleConfigData($ext_module[0]);
            $e_fields = $this->wire(new $ext_module[1]($e_data))->getFields();
            foreach ($e_fields as $e_field) {
                $e_field->name .= '_' . $ext_key;
                if ($e_field->collapsed == Inputfield::collapsedHidden) {
                    $e_field->collapsed = Inputfield::collapsedNo;
                } else {
                    $e_field->collapsed = Inputfield::collapsedHidden;
                }
                if ($e_field instanceof InputfieldWrapper) {
                    foreach ($e_field as $e_subfield) {
                        $e_subfield->name .= '_' . $ext_key;
                        if ($e_subfield->showIf) {
                            $e_subfield->showIf = str_replace('=', '_' . $ext_key . '=', $e_subfield->showIf);
                        }
                        $e_field->add($e_subfield);
                    }
                }
            }
            $fields->add($e_fields);
        }

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
        $incompatible_fieldtype_options = [
            'FieldtypePassword',
            'FieldtypeFieldsetOpen',
            'FieldtypeFieldsetClose',
            'FieldtypeFieldsetTabOpen',
            'FieldtypeFieldsetTabClose',
            'FieldtypeFieldsetPage',
        ];
        foreach ($modules->find('className^=Fieldtype') as $fieldtype) {
            if (in_array($fieldtype->name, $incompatible_fieldtype_options)) {
                continue;
            }
            $field->addOption($fieldtype->name);
        }
        if (isset($data['compatible_fieldtypes'])) $field->value = $data['compatible_fieldtypes'];
        $field->notes = $this->_("Please note that selecting any fieldtypes not selected by default may result in various problems.");
        $field->notes .= $this->getCompatibleFieldtypeDiff($field->value);
        $fieldset->add($field);

        // enable version control for *all* templates
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "enable_all_templates";
        $field->label = $this->_("Enable version control for all templates");
        $field->description = $this->_("If this option is selected, Version Control will track changes to all templates.");
        $field->notes = $this->_("This could result in very large amounts of collected data, and cause other unexpected side effects!");
        $field->attr('checked', isset($data['enable_all_templates']) && $data['enable_all_templates']);
        $fieldset->add($field);

        // enable access to Version Control managed files in secure pagefile directories
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "secure_file_access";
        $field->label = $this->_("Allow access to secure files");
        $field->description = $this->_("If secure page files are enabled and user attempts to view an earlier version of a file without restoring it first, ProcessWire will block access to said file. By default we attempt to circumvent this if requested file appears to be one managed by Version Control and current user has edit access to related page, but in case file directories are used to store other non-core content there's always the possiblity of something being unintentionally made viewable.");
        $field->notes = $this->_("This option is enabled by default. If you disable it, users will not be able to preview files for non-public pages if core secure page file option is enabled.");
        $fieldset->add($field);
        $field->attr('checked', isset($data['secure_file_access']) && $data['secure_file_access']);

        return $fields;
    }

    /**
     * Get a list of changes (additions and removals) made to the compatible fieldtypes setting
     *
     * @param array $compatible_fieldtypes Current list of compatible fieldtypes.
     * @return string String representation of the changes.
     */
    protected function getCompatibleFieldtypeDiff(array $compatible_fieldtypes): string {

        // get a diff by comparing module default setting value and current setting value
        $base = VersionControl::$defaultData['compatible_fieldtypes'];
        $diff = array_filter([
            'added' => implode(', ', array_diff($compatible_fieldtypes, $base)),
            'removed' => implode(', ', array_filter(array_diff($base, $compatible_fieldtypes), function($fieldtype) {
                return $this->wire('modules')->isInstalled($fieldtype);
            })),
        ]);

        // construct output string
        $out = "";
        if (!empty($diff)) {
            $out .= "\n";
            if (!empty($diff['added'])) {
                $out .= "\n+ " . sprintf($this->_('Added fieldtypes: %s'), $diff['added']);
            }
            if (!empty($diff['removed'])) {
                $out .= "\n- " . sprintf($this->_('Removed fieldtypes: %s'), $diff['removed']);
            }
        }

        return $out;
    }

}
