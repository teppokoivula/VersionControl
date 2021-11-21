<?php

namespace VersionControl;

/**
 * Version Control Data Object
 *
 * This is an abstract base class for different types of data objects.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
abstract class DataObject extends \ProcessWire\Wire {

    /**
     * Common prefix for database tables
     *
     * @var string
     */
    const TABLE_PREFIX = 'version_control__';

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
        $stmt = $this->database->prepare('SHOW TABLES LIKE \'' . $table . '\'');
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            throw new WireDatabaseException(sprintf(
                i18n::getText('Table %s already exists'),
                $table
            ));
        }
        $this->database->query('CREATE TABLE ' . $table . ' (' . implode(', ', $schema) . ') ENGINE = ' . $engine . ' DEFAULT CHARSET=' . $charset);
        $this->message(sprintf(
            i18n::getText('Created table: %s'),
            $table
        ));
    }

    /**
     * Helper method for dropping tables
     *
     * @param string $table Table name
     */
    protected function dropTable(string $table) {
        $table = $this->database->escapeStr($table);
        $stmt = $this->database->prepare('SHOW TABLES LIKE \'' . $table . '\'');
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            $this->database->query('DROP TABLE ' . $table);
            $this->message(sprintf(
                i18n::getText('Dropped table: %s'),
                $table
            ));
        }
    }

}
