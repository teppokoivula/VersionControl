<?php

namespace VersionControl\Data;

use VersionControl\DataObject;

use ProcessWire\Page;

/**
 * Version Control Data
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class Data extends DataObject {

    /**
     * Name of the database table containing actual field values
     *
     * @var string
     */
    const TABLE = DataObject::TABLE_PREFIX . 'data';

    /**
     * Set up data structures
     */
    public function install() {
        $this->createTable(self::TABLE, [
            'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'revisions_id INT UNSIGNED NOT NULL',
            'fields_id INT UNSIGNED NOT NULL',
            'property VARCHAR(255) NOT NULL',
            'data MEDIUMTEXT DEFAULT NULL',
            'KEY revisions_id (revisions_id)',
            'KEY fields_id (fields_id)',
        ]);
    }

    /**
     * Tear down data structures
     */
    public function uninstall() {
        $this->dropTable(self::TABLE);
    }

    /**
     * Get page data, optionally matching provided revision ID and/or timestamp
     *
     * Note that this method contains two distinct SQL queries, depending on whether we're looking for page data in a
     * specific revision or at specific time, or if we're looking for data for page spanning over multiple revisions.
     * For consistency both queries return an identical set of columns:
     *
     * ```
     * [0] => Array
     *     (
     *         [pages_id] => 7477
     *         [revision] => 5728
     *         [fields_id] => 1
     *         [property] => data
     *         [data] => So Long, and Thanks for All the Fish
     *         [field_name] => title
     *         [timestamp] => 1980-05-01 08:34:50
     *         [users_id] => 42
     *         [username] => arthur
     *     )
     * // etc.
     * ```
     *
     * @param array $page_ids
     * @param int|null $time
     * @param int|null $revision_id
     * @return array|null
     */
    public function getForPage(array $page_ids, ?int $time = null, ?int $revision_id = null): ?array {
        $page_ids = array_values(array_filter($page_ids, 'is_int'));
        if (empty($page_ids)) {
            return null;
        }
        $stmt = null;
        if ($time || $revision_id) {
            // if timestamp or revision ID is provided, we'll need a nested subquery to get the latest combination of
            // page/field/property for our page (and any nested pages, such as repeater items)
            $stmt = $this->database->prepare('
            SELECT
                r.pages_id, r.id revision, d.fields_id, d.property, d.data,
                f.name field_name, r.timestamp, r.users_id, r.username
            FROM (
                SELECT
                    MAX(r.id) id, r.pages_id, d.fields_id,
                    MAX(r.users_id) users_id, MAX(r.username) username, MAX(r.timestamp) timestamp
                FROM ' . Revisions::TABLE . ' r, ' . self::TABLE . ' d
                WHERE
                    r.pages_id IN (:p' . implode(', :p', array_keys($page_ids)) . ')
                    AND d.revisions_id = r.id
                    ' . ($revision_id ? 'AND r.id <= :revision_id' : '') . '
                    ' . ($time ? 'AND r.timestamp <= :time' : '') . '
                GROUP BY r.pages_id, d.fields_id, d.property
            ) r
            INNER JOIN ' . self::TABLE . ' d
                ON d.revisions_id = r.id AND d.fields_id = r.fields_id
            INNER JOIN fields f
                ON f.id = d.fields_id
            GROUP BY revision, r.pages_id, d.fields_id, d.property, d.data
            ORDER BY revision ASC
            ');
            if ($revision_id) {
                $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
            }
            if ($time) {
                $stmt->bindValue(':time', date('Y-m-d H:i:s', $time), \PDO::PARAM_STR);
            }
        } else {
            // we're interested in the data for provided page/pages, including past revisions, with newer revisions
            // appearing before older revisions
            $stmt = $this->database->prepare('
            SELECT
                r.pages_id, r.id revision, d.fields_id, d.property, d.data,
                f.name field_name, r.timestamp, r.users_id, r.username
            FROM ' . Revisions::TABLE . ' r
            INNER JOIN ' . self::TABLE . ' d
                ON d.revisions_id = r.id
            INNER JOIN fields f
                ON f.id = d.fields_id
            WHERE
                r.pages_id IN (:p' . implode(', :p', array_keys($page_ids)) . ')
            GROUP BY
                r.pages_id, revision, d.fields_id, d.property, d.data,
                field_name, r.timestamp, users_id, r.username, d.id
            ORDER BY r.pages_id, d.fields_id, d.id DESC
            ');
        }
        foreach ($page_ids as $p_num => $p_id) {
            $stmt->bindValue(':p' . $p_num, $p_id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $data === false ? null : $data;
    }

    /**
     * Get field data with revision ID(s)
     *
     * @param int|string|Field $field
     * @param int|array $revision_ids
     * @return array|null
     */
    public function getForField($field, $revision_ids): ?array {

        // validate field ID
        $fields_id = $this->getFieldID($field);
        if ($fields_id === null) {
            return null;
        }

        // validate revision ID(s)
        if (is_int($revision_ids)) {
            $revision_ids = [$revision_ids];
        } else {
            $revision_ids = array_values(array_filter($revision_ids, 'is_int'));
            if (empty($revision_ids)) {
                return null;
            }
        }

        // fetch and return data
        $stmt = $this->database->prepare("
        SELECT r.id revision, r.pages_id, d.fields_id, d.property, d.data
        FROM " . Revisions::TABLE . " r, " . self::TABLE . " d
        WHERE d.fields_id = :fields_id AND r.id IN(:r" . implode(', :r', array_keys($revision_ids)) . ") AND d.revisions_id = r.id
        ORDER BY revision
        ");
        $stmt->bindValue(':fields_id', $fields_id, \PDO::PARAM_INT);
        foreach ($revision_ids as $r_num => $r_id) {
            $stmt->bindValue(':r' . $r_num, $r_id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $data === false ? null : $data;
    }

    /**
     * Store field data in database
     *
     * @param int|string|Field $field
     * @param array $field_data
     * @param int $revisions_id
     */
    public function saveForField($field, array $field_data, int $revisions_id) {
        $fields_id = $this->getFieldID($field);
        if ($fields_id === null) {
            return;
        }
        foreach ($field_data as $property => $data) {

            $file = null;

            // dot means that this is multipart property (n.data), which can (at least for the time being) be used to
            // identify file/image fields
            if (strpos($property, ".") && !is_null($data)) {
                $data = json_decode($data, true);
                $file = [
                    'filename' => $data['filename'] ?? '',
                    'source_filename' => $data['_original_filename'] ?? null,
                ];
                unset($data['_original_filename']);
                $data = json_encode($data);
            }

            // insert field data to the data table
            $stmt = $this->database->prepare("INSERT INTO " . self::TABLE . " (revisions_id, fields_id, property, data) VALUES (:revisions_id, :fields_id, :property, :data)");
            $stmt->bindValue(':revisions_id', $revisions_id, \PDO::PARAM_INT);
            $stmt->bindValue(':fields_id', $fields_id, \PDO::PARAM_INT);
            $stmt->bindValue(':property', $property, \PDO::PARAM_STR);
            $stmt->bindValue(':data', $data, \PDO::PARAM_STR);
            $data_id = $stmt->execute() ? $this->database->lastInsertId() : null;

            // if data row is related to a file, store file data
            if ($file !== null) {
                (new Files)->add($file['filename'], $file['source_filename'], $data_id);
            }
        }
    }

    /**
     * Remove data for provided template, optionally limited to specific field(s)
     *
     * @param int $templates_id
     * @param int|string|Field|array|null $fields
     * @return bool
     */
    public function deleteForTemplate(int $templates_id, $fields = null): bool {
        if ($fields !== null) {
            $field_ids = array_filter(is_array($fields) ? array_map(function($field) {
                return $this->getFieldID($field);
            }, $fields) : [$this->getFieldID($fields)]);
            if (empty($field_ids)) {
                return false;
            }
            $stmt = $this->database->prepare("
            DELETE FROM " . self::TABLE . "
            WHERE fields_id IN (:f" . implode(', :f', array_keys($field_ids)) . ")
            AND revisions_id IN (
                SELECT id FROM " . Revisions::TABLE . "
                WHERE pages_id IN (
                    SELECT id FROM pages WHERE templates_id = :templates_id
                )
            )
            ");
            foreach ($field_ids as $f_num => $f_id) {
                $stmt->bindValue(':f' . $f_num, $f_id, \PDO::PARAM_INT);
            }
            $stmt->bindValue(':templates_id', $templates_id, \PDO::PARAM_INT);
            return $stmt->execute();
        }
        $stmt = $this->database->prepare("
        DELETE " . Revisions::TABLE . ", " . self::TABLE . "
        FROM " . Revisions::TABLE . ", " . self::TABLE . "
        WHERE " . Revisions::TABLE . ".pages_id IN (
            SELECT id FROM pages WHERE templates_id = " . $templates_id . "
        )
        AND " . self::TABLE . ".revisions_id = " . Revisions::TABLE . ".id
        ");
        return $stmt->execute();
    }

    /**
     * Remove data for provided field
     *
     * @param int|string|Field $field
     * @return bool
     */
    public function deleteForField($field): bool {
        $fields_id = $this->getFieldID($field);
        if ($fields_id === null) {
            return false;
        }
        $stmt = $this->database->prepare("DELETE FROM " . self::TABLE . " WHERE fields_id = :fields_id");
        $stmt->bindValue(':fields_id', $fields_id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Remove data for provided page
     *
     * @param Page $page
     */
    public function deleteForPage(Page $page): bool {
        if (!$page->id) {
            return false;
        }
        $stmt = $this->database->prepare("
        DELETE " . Revisions::TABLE . ", " . self::TABLE . "
        FROM " . Revisions::TABLE . "
        LEFT OUTER JOIN " . self::TABLE . " ON " . self::TABLE . ".revisions_id = " . Revisions::TABLE . ".id
        WHERE " . Revisions::TABLE . ".pages_id = :pages_id
        ");
        $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Remove data older than provided max age (interval)
     *
     * @param string $max_age
     * @return bool
     */
    public function purge(string $max_age): bool {
        $max_age = $this->database->escapeStr($max_age);
        if (empty($max_age)) {
            return false;
        }
        $stmt = $this->database->prepare("
        DELETE " . Revisions::TABLE . ", " . self::TABLE . "
        FROM " . Revisions::TABLE . ", " . self::TABLE . "
        WHERE " . Revisions::TABLE . ".timestamp < DATE_SUB(NOW(), INTERVAL " . $max_age . ")
        AND " . self::TABLE . ".revisions_id = " . Revisions::TABLE . ".id
        ");
        return $stmt->execute();
    }

    /**
     * Get field ID based on mixed input
     *
     * @param int|string|Field $field
     * @return int|null
     */
    protected function getFieldID($field): ?int {
        if (is_int($field)) {
            return $field;
        }
        if (is_string($field)) {
            $fields_name = strpos($field, '_repeater') ? preg_replace('/_repeater[0-9]+$/', '', $field) : $field;
            $fields_name = $this->sanitizer->fieldName($fields_name);
            $field = $this->fields->get($fields_name);
            return $field->id;
        }
        if ($field instanceof Field) {
           return$field->id;
        }
        return null;
    }

}
