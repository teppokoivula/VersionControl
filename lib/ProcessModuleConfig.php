<?php

namespace VersionControl;

use ProcessWire\InputfieldWrapper,
    ProcessWire\Inputfield,
    ProcessWire\VersionControl,
    ProcessWire\ProcessVersionControl;

/**
 * Process Version Control Config
 *
 * @version 1.0.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class ProcessModuleConfig extends \ProcessWire\Wire {

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
        $defaults = ProcessVersionControl::$defaultData;
        $data = array_merge($defaults, $data);

        // notice about centralized configuration
        $field = $modules->get("InputfieldMarkup");
        $field->label = $this->_("Configuring the Version Control module bundle");
        $field->icon = "info-circle";
        $link_module = "VersionControl";
        $link_markup = '<a href="' . $this->wire('page')->url . 'edit?name=' . $link_module . '">' . $link_module . '</a>';
        $field->set('markupText', sprintf(
            $this->_("All configuration settings for the Version Control module bundle can be found from the %s module."),
            $link_markup
        ));
        $fields->add($field);

        // fieldset: output settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("Output Settings");
        $fieldset->icon = "eyedropper";
        $fieldset->collapsed = Inputfield::collapsedHidden;
        $fields->add($fieldset);

        // date format used
        $field = $modules->get("InputfieldText");
        $field->name = "date_format";
        $field->label = $this->_("Date Format");
        $field->description = $this->_("Used when displaying version history data in page edit.");
        $field->notes = $this->_("See the [PHP date](http://www.php.net/manual/en/function.date.php) function reference for more information on how to customize this format.");
        $field->value = $data['date_format'] ? $data['date_format'] : $defaults['date_format'];
        $field->icon = "clock-o";
        $fieldset->add($field);

        // user name format
        $field = $modules->get("InputfieldText");
        $field->name = "user_name_format";
        $field->label = $this->_("User Name Format");
        $field->description = $this->_("This defines the format and field(s) used to represent user names.");
        $field->notes = $this->_("This string is passed to wirePopulateStringTags() function. Example: {name} ({email}).");
        $field->value = $data[$field->name];
        $field->icon = "user";
        $fieldset->add($field);

        // fieldset: diff settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("Diff Settings");
        $fieldset->icon = "files-o";
        $fieldset->collapsed = Inputfield::collapsedHidden;
        $fields->add($fieldset);

        // disable diff feature
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "diff_disabled";
        $field->label = $this->_("Disable diff");
        if (isset($data[$field->name]) && $data[$field->name]) $field->checked = "checked";
        $fieldset->add($field);

        // diff timeout
        $field = $modules->get("InputfieldInteger");
        $field->name = "diff_timeout";
        $field->label = $this->_("Diff Timeout");
        $field->description = $this->_("If diff computation takes longer than this, best solution to date is returned. While correct, it may not be optimal.");
        $field->notes = $this->_("A timeout of '0' allows for unlimited computation.");
        $field->showIf = "diff_disabled=0";
        $field->value = $data[$field->name];
        $fieldset->add($field);

        // diff cleanup
        $field = $modules->get("InputfieldRadios");
        $field->name = "diff_cleanup";
        $field->label = $this->_("Post-diff Cleanup");
        $field->description = $this->_("Post-diff cleanup algorithms attempt to filter out irrelevant small commonalities, thus enhancing final output.");
        $field->notes = $this->_("See [Diff Demo](https://neil.fraser.name/software/diff_match_patch/svn/trunk/demos/demo_diff.html) for examples and detailed descriptions.");
        $field->addOptions([
                '' => $this->_("No Cleanup"),
                'semantic' => $this->_("Semantic Cleanup"),
                'efficiency' => $this->_("Efficiency Cleanup"),
        ]);
        $field->showIf = "diff_disabled=0";
        if (isset($data[$field->name])) $field->value = $data[$field->name];
        $fieldset->add($field);

        // diff efficiency cleanup edit cost
        $field = $modules->get("InputfieldInteger");
        $field->name = "diff_efficiency_cleanup_edit_cost";
        $field->label = $this->_("Efficiency Cleanup Edit Cost");
        $field->description = $this->_("The larger the edit cost, the more agressive the cleanup.");
        $field->showIf = "diff_disabled=0,diff_cleanup=efficiency";
        if (isset($data[$field->name])) $field->value = $data[$field->name];
        $fieldset->add($field);

        return $fields;
    }

}
