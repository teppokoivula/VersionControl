<?php

namespace VersionControl;

/**
 * Version Control Data Store
 *
 * This class is responsible for managing data stored and used by the Version Control module. Data store interacts with
 * individual data objects, which in turn encapsulate database queries and direct file operations.
 *
 * @property-read \VersionControl\Data\Revisions $revisions
 * @property-read \VersionControl\Data\Data $data
 * @property-read \VersionControl\Data\Files $files
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class DataStore extends \ProcessWire\Wire {

    /**
     * Array of all available data objects
     *
     * @var array
     */
    protected $data_objects = [
        'revisions' => '\VersionControl\Data\Revisions',
        'data' => '\VersionControl\Data\Data',
        'files' => '\VersionControl\Data\Files',
    ];

    /**
     * Revisions data object
     *
     * @var \VersionControl\Data\Revisions
     */
    protected $revisions;

    /**
     * Data data object
     *
     * @var \VersionControl\Data\Data
     */
    protected $data;

    /**
     * Files data object
     *
     * @var \VersionControl\Data\Files
     */
    protected $files;

    /**
     * Set up required data structures
     */
    public function install() {
        array_walk($this->data_objects, function(string $class_name, string $data_object) {
            ($this->$data_object ?: $this->__get($data_object))->install();
        });
        $this->importVersionControlForTextFieldsData();
    }

    /**
     * Tear down data structures
     */
    public function uninstall() {
        array_walk($this->data_objects, function(string $class_name, string $data_object) {
            ($this->$data_object ?: $this->__get($data_object))->uninstall();
        });
    }

    /**
     * Import data from Version Control For Text Fields
     */
    protected function importVersionControlForTextFieldsData() {
        $main_table = count($this->database->query("SHOW TABLES LIKE 'version_control_for_text_fields'")->fetchAll()) == 1;
        $data_table = count($this->database->query("SHOW TABLES LIKE 'version_control_for_text_fields__data'")->fetchAll()) == 1;
        if ($main_table && $data_table) {
            $stmt_select_data_row = $this->database->prepare("SELECT property, data FROM version_control_for_text_fields__data WHERE version_control_for_text_fields_id = :id");
            $stmt_insert_revision = $this->database->prepare("INSERT INTO " . Revisions::TABLE . " (parent, pages_id, users_id, username, timestamp) VALUES (:parent, :pages_id, :users_id, :username, :timestamp)");
            $stmt_insert_data_row = $this->database->prepare("INSERT INTO " . Data::TABLE . " (revisions_id, fields_id, property, data) VALUES (:revisions_id, :fields_id, :property, :data)");
            $result = $this->database->query("SELECT * FROM version_control_for_text_fields");
            $parent = null;
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                $stmt_insert_revision->bindValue(':parent', $parent, \PDO::PARAM_INT);
                $stmt_insert_revision->bindValue(':pages_id', (int) $row['pages_id'], \PDO::PARAM_INT);
                $stmt_insert_revision->bindValue(':users_id', (int) $row['users_id'], \PDO::PARAM_INT);
                $stmt_insert_revision->bindValue(':username', $row['username'], \PDO::PARAM_STR);
                $stmt_insert_revision->bindValue(':timestamp', $row['timestamp'], \PDO::PARAM_STR);
                $stmt_insert_revision->execute();
                $revisions_id = $this->database->lastInsertId();
                $stmt_select_data_row->bindValue(':id', (int) $row['id'], \PDO::PARAM_INT);
                $stmt_select_data_row->execute();
                while ($data_row = $stmt_select_data_row->fetch(\PDO::FETCH_ASSOC)) {
                    $stmt_insert_data_row->bindValue(':revisions_id', $revisions_id, \PDO::PARAM_INT);
                    $stmt_insert_data_row->bindValue(':fields_id', (int) $row['fields_id'], \PDO::PARAM_INT);
                    $stmt_insert_data_row->bindValue(':property', $data_row['property'], \PDO::PARAM_STR);
                    $stmt_insert_data_row->bindValue(':data', $data_row['data'], \PDO::PARAM_STR);
                    $stmt_insert_data_row->execute();
                }
                $parent = $revisions_id;
            }
            $this->message(i18n::getText('Imported existing Version Control For Text Fields data'));
        }
    }

    /**
     * Magic getter method
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name) {
        if (isset($this->data_objects[$name])) {
            if ($this->$name === null) {
                $this->$name = new $this->data_objects[$name];
            }
            return $this->$name;
        }
        return parent::__get($name);
    }

}
