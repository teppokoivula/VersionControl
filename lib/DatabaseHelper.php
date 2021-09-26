<?php

namespace VersionControl;

use \ProcessWire\VersionControl;
use \ProcessWire\WireDatabaseException;

/**
 * Version Control Database Helper
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class DatabaseHelper extends \ProcessWire\Wire {

	/**
	 * Create database tables
	 */
	public function createTables() {

		// revisions table bundles individual data rows into site-wide revisions
		$this->createTable(VersionControl::TABLE_REVISIONS, [
			'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'parent INT UNSIGNED DEFAULT NULL',
			'pages_id INT UNSIGNED NOT NULL',
			'users_id INT UNSIGNED DEFAULT NULL',
			'username VARCHAR(255) DEFAULT NULL',
			'timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
			'comment VARCHAR(255) DEFAULT NULL',
			'KEY pages_id (pages_id)',
		]);

		// data table, contains actual content for edited fields
		$this->createTable(VersionControl::TABLE_DATA, [
			'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'revisions_id INT UNSIGNED NOT NULL',
			'fields_id INT UNSIGNED NOT NULL',
			'property VARCHAR(255) NOT NULL',
			'data MEDIUMTEXT DEFAULT NULL',
			'KEY revisions_id (revisions_id)',
			'KEY fields_id (fields_id)',
		]);

		// files table contains one row for each stored file
		$this->createTable(VersionControl::TABLE_FILES, [
			'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'filename VARCHAR(255) NOT NULL',
			'mime_type VARCHAR(255)',
			'size INT UNSIGNED NOT NULL',
		]);

		// junction table: connects files to data rows
		$this->createTable(VersionControl::TABLE_DATA_FILES, [
			'data_id INT UNSIGNED NOT NULL',
			'files_id INT UNSIGNED NOT NULL',
			'PRIMARY KEY (data_id, files_id)',
		]);
	}

	/**
	 * Drop database tables
	 */
	public function dropTables() {
		$this->dropTable(VersionControl::TABLE_REVISIONS);
        $this->dropTable(VersionControl::TABLE_DATA);
        $this->dropTable(VersionControl::TABLE_FILES);
        $this->dropTable(VersionControl::TABLE_DATA_FILES);
	}

	/**
     * Helper method for dropping tables
     *
     * @param string $table Table name
     */
    protected function dropTable(string $table) {
        $table = $this->database->escapeStr($table);
        $stmt = $this->database->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            $this->database->query("DROP TABLE $table");
            $this->message(sprintf(
                i18n::getText('Dropped table: %s'),
                $table
            ));
        }
    }

    /**
     * Helper method for creating tables
     *
     * @param string $table Table name
     * @param array $schema Table schema
     * @throws WireDatabaseException if table already exists
     */
    protected function createTable(string $table, array $schema) {
        $table = $this->database->escapeStr($table);
        $engine = $this->wire('config')->dbEngine;
        $charset = $this->wire('config')->dbCharset;
        $stmt = $this->database->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            throw new WireDatabaseException(sprintf(
                i18n::getText('Table %s already exists'),
                $table
            ));
        }
        $sql = "CREATE TABLE $table (";
        $sql .= implode(', ', $schema);
        $sql .= ") ENGINE = " . $engine . " DEFAULT CHARSET=" . $charset;
        $this->database->query($sql);
        $this->message(sprintf(
            i18n::getText('Created table: %s'),
            $table
        ));
    }


    /**
     * Import data from Version Control For Text Fields
     */
    protected function versionControlForTextFieldsImport() {
        $main_table = count($this->database->query("SHOW TABLES LIKE 'version_control_for_text_fields'")->fetchAll()) == 1;
        $data_table = count($this->database->query("SHOW TABLES LIKE 'version_control_for_text_fields__data'")->fetchAll()) == 1;
        if ($main_table && $data_table) {
            $stmt_select_data_row = $this->database->prepare("SELECT property, data FROM version_control_for_text_fields__data WHERE version_control_for_text_fields_id = :id");
            $stmt_insert_revision = $this->database->prepare("INSERT INTO " . VersionControl::TABLE_REVISIONS . " (parent, pages_id, users_id, username, timestamp) VALUES (:parent, :pages_id, :users_id, :username, :timestamp)");
            $stmt_insert_data_row = $this->database->prepare("INSERT INTO " . VersionControl::TABLE_DATA . " (revisions_id, fields_id, property, data) VALUES (:revisions_id, :fields_id, :property, :data)");
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

}
