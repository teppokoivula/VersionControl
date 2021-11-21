<?php

namespace VersionControl;

use ProcessWire\InputfieldWrapper;
use ProcessWire\Inputfield;
use ProcessWire\VersionControl;
use ProcessWire\VersionControlCleanup;

/**
 * Version Control Cleanup Config
 *
 * @version 1.0.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class CleanupModuleConfig extends \ProcessWire\Wire {

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
        $defaults = VersionControlCleanup::$defaultData;
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

        // fieldset: cleanup settings
        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = $this->_("Cleanup Settings");
        $fieldset->icon = "trash-o";
        $fieldset->collapsed = Inputfield::collapsedHidden;
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

        return $fields;
    }

}
