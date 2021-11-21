<?php

namespace VersionControl\Data;

use VersionControl\DataObject;

use ProcessWire\Page;
use ProcessWire\User;

/**
 * Version Control Revisions
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class Revisions extends DataObject {

    /**
     * Name of the revisions database table
     *
     * Revisions table keeps track of revision numbers, which are system wide, and stores important metadata, such as
     * dates, user IDs, and page IDs.
     *
     * @var string
     */
    const TABLE = DataObject::TABLE_PREFIX . 'revisions';

    /**
     * Set up data structures
     */
    public function install() {
        $this->createTable(self::TABLE, [
            'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'parent INT UNSIGNED DEFAULT NULL',
            'pages_id INT UNSIGNED NOT NULL',
            'users_id INT UNSIGNED DEFAULT NULL',
            'username VARCHAR(255) DEFAULT NULL',
            'timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'comment VARCHAR(255) DEFAULT NULL',
            'KEY pages_id (pages_id)',
        ]);
    }

    /**
     * Tear down data structures
     */
    public function uninstall() {
        $this->dropTable(self::TABLE);
    }

    /**
     * Add new revision
     *
     * @param Page $page
     * @param User $user
     * @return int|null Revision ID, or null in case of a failure
     */
    public function add(Page $page, User $user): ?int {

        // bail out early if page doesn't have an ID (NullPage)
        if (!$page->id) {
            return null;
        }

        // add new row to the revisions database table
        $stmt = $this->database->prepare("INSERT INTO " . self::TABLE . " (parent, pages_id, users_id, username, timestamp) VALUES (:parent, :pages_id, :users_id, :username, :timestamp)");
        $stmt->bindValue(':parent', $page->_version_control_parent, \PDO::PARAM_INT);
        $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
        $stmt->bindValue(':users_id', $user->id, \PDO::PARAM_INT);
        $stmt->bindValue(':username', $user->name, \PDO::PARAM_STR);
        $stmt->bindValue(':timestamp', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();
        $revision_id = $this->database->lastInsertId();

        // if parent isn't assigned yet, attempt to fetch and use the ID of the previous revision for this page
        if (!$page->_version_control_parent) {
            $stmt = $this->database->prepare("SELECT id FROM " . self::TABLE . " WHERE pages_id = :pages_id AND id < :revision_id ORDER BY id DESC LIMIT 1");
            $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
            $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result) {
                $stmt = $this->database->prepare("UPDATE " . self::TABLE . " SET parent = :parent WHERE id = :revision_id");
                $stmt->bindValue(':parent', (int) $result['id'], \PDO::PARAM_INT);
                $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        return $revision_id;
    }

    /**
     * Update revision data
     *
     * @param int $revision_id
     * @param array $data
     * @return array|null Stored data
     */
    public function update(int $revision_id, array $data): ?array {

        // filter and validate data array
        $allowed_keys = [
            'parent' => [
                'type' => \PDO::PARAM_INT,
            ],
            'pages_id' => [
                'type' => \PDO::PARAM_INT,
            ],
            'users_id' => [
                'type' => \PDO::PARAM_INT,
            ],
            'username' => [
                'type' => \PDO::PARAM_STR,
            ],
            'timestamp' => [
                'type' => \PDO::PARAM_STR,
            ],
            'comment' => [
                'type' => \PDO::PARAM_STR,
                'func' => function($value) {
                    return mb_strlen($value) > 255 ? mb_substr($value, 0, 255) : $value;
                },
            ],
        ];
        $data = array_filter($data, function($key) use ($allowed_keys) {
            return isset($allowed_keys[$key]);
        }, ARRAY_FILTER_USE_KEY);
        if (empty($data)) {
            return false;
        }

        // update data
        $stmt = $this->database->prepare('
        UPDATE ' . self::TABLE . '
        SET ' . implode(', ', array_map(function($key) { return $key . ' = :' . $key; }, array_keys($data))) . '
        WHERE id = :revision_id
        ');
        $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
        foreach ($data as $key => &$value) {
            if (isset($allowed_keys[$key]['func'])) {
                $value = $allowed_keys[$key]['func']($value);
            }
            $stmt->bindValue(':' . $key, $value, $allowed_keys[$key]['type']);
        }
        $status = $stmt->execute();

        // return updated data, or null in case of a failure
        return $status === false ? null : $data;
    }

    /**
     * Get revision data
     *
     * @param int $revision_id
     * @param array $keys
     * @return array|null
     */
    public function getData(int $revision_id, array $keys = []): ?array {

        // filter and validate keys array
        $allowed_keys = [
            'id',
            'parent',
            'pages_id',
            'users_id',
            'username',
            'timestamp',
            'comment',
        ];
        if (empty($keys)) {
            $keys = $allowed_keys;
        } else {
            $keys = array_filter($keys, function($key) use ($allowed_keys) {
                return in_array($key, $allowed_keys);
            });
            if (empty($keys)) {
                return null;
            }
        }

        // fetch and return data
        $stmt = $this->database->prepare("SELECT " . implode(", ", $keys) . " FROM " . self::TABLE . " WHERE id = :revision_id");
        $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
        $stmt->execute();
        $revision = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $revision === false ? null : $revision;
    }

    /**
     * Get an array of users related to provided page
     *
     * @param Page $page
     * @return array
     */
    public function getPageUsers(Page $page): array {
        if (!$page->id) {
            return [];
        }
        $users = [];
        $stmt = $this->database->prepare("SELECT DISTINCT users_id FROM " . self::TABLE . " WHERE pages_id = :pages_id");
        $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $user = $this->wire('users')->get((int) $row['users_id']);
            if ($user->id) {
                $users[$user->name] = $user->id;
                continue;
            }
            $stmt = $this->database->prepare("SELECT username FROM " . self::TABLE . " WHERE users_id = :users_id LIMIT 1");
            $stmt->bindValue(':users_id', $row['users_id'], \PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user !== false) {
                $users[$user['username']] = $row['users_id'];
            }
        }
        ksort($users);
        $users = array_flip($users);
        return $users;
    }

    /**
     * Get current revision ID for provided page
     *
     * @param Page $page
     * @return int|null $revisions_id
     */
    public function getPageRevision(Page $page): ?int {
        if (!$page->id) {
            return null;
        }
        $stmt = $this->database->prepare("SELECT id FROM " . self::TABLE . " WHERE pages_id = :pages_id ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result === false ? null : (int) $result['id'];
    }

    /**
     * Get a list of revisions (id => timestamp) for provided page
     *
     * @param Page $page
     * @param int|null $limit
     * @return array|null
     */
    public function getPageRevisions(Page $page, ?int $limit = null): ?array {
        if (!$page->id) {
            return null;
        }
        $sql = "SELECT id, timestamp FROM " . self::TABLE . " WHERE pages_id = :pages_id ORDER BY id DESC";
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
        if ($limit) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $revisions = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $revisions === false ? null : $revisions;
    }

    /**
     * Get full history for provided page
     *
     * This method primarily exists for the "history" tab in admin.
     *
     * @param Page $page
     * @param int|null $start
     * @param int|null $limit
     * @param array $filters
     * @return array|null
     */
    public function getPageHistory(Page $page, ?int $start = null, ?int $limit = null, array $filters = []): array {

        // bail out early if page doesn't have an ID (NullPage)
        if (!$page->id) {
            return [
                'rows' => [],
                'total' => 0,
            ];
        }

        // prepare WHERE rules (page, filters)
        $where = [];
        $where['r.pages_id = :pages_id'] = [':pages_id', $page->id, \PDO::PARAM_INT];
        if (isset($filters['users_id']) && $filters['users_id'] == (int) $filters['users_id']) {
            $where['r.users_id = :users_id'] = [':users_id', $filters['users_id'], \PDO::PARAM_INT];
        }
        $where_str = "WHERE " . implode(" AND ", array_keys($where));

        // fetch the total count of matching rows
        $stmt = $this->database->prepare("SELECT COUNT(*) AS total FROM " . self::TABLE . " r " . $where_str);
        foreach ($where as $value) {
            $stmt->bindValue($value[0], $value[1], $value[2]);
        }
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = $result === false ? 0 : (int) $result['total'];

        // bail out early if there were no results
        if ($total === 0) {
            return [
                'rows' => [],
                'total' => 0,
            ];
        }

        // prepare LIMIT clause
        if ($limit) {
            $start = $start ?? 0;
            if ($start > $total) {
                $start = $total - $limit;
                $start = $start < 0 ? 0 : $start;
            }
        }

        // prepare and execute SQL query
        $sql = "
        SELECT r.id, r.users_id, r.username, GROUP_CONCAT(CONCAT_WS(':', d.fields_id, d.property)) changes, r.timestamp, r.comment
        FROM " . self::TABLE . " r
        LEFT OUTER JOIN " . Data::TABLE . " d
        ON d.revisions_id = r.id
        " . $where_str . "
        GROUP BY r.id
        ORDER BY r.timestamp DESC, r.id DESC
        ";
        if ($limit) {
            $sql .= "LIMIT :start, :limit";
        }
        $stmt = $this->database->prepare($sql);
        foreach ($where as $value) {
            $stmt->bindValue($value[0], $value[1], $value[2]);
        }
        if ($limit) {
            $stmt->bindValue(':start', $start, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();

        // fetch history rows
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'rows' => $rows === false ? [] : $rows,
            'total' => $total,
        ];
    }

    /**
     * Get revision info for provided page(s)
     *
     * @param array $page_ids
     * @return array|null
     */
    public function getForPage(array $page_ids): ?array {
        $page_ids = array_values(array_filter($page_ids, 'is_int'));
        if (empty($page_ids)) {
            return null;
        }
        $stmt = $this->database->prepare('
        SELECT
            r.pages_id, r.id revision, d.fields_id,
            f.name field_name, r.timestamp, r.users_id, r.username
        FROM ' . self::TABLE . ' r
        INNER JOIN ' . Data::TABLE . ' d
            ON d.revisions_id = r.id
        INNER JOIN fields f
            ON f.id = d.fields_id
        WHERE
            r.pages_id IN (:p' . implode(', :p', array_keys($page_ids)) . ')
        GROUP BY
            r.pages_id, revision, d.fields_id,
            field_name, r.timestamp, r.users_id, r.username
        ORDER BY r.pages_id, d.fields_id, revision DESC
        ');
        foreach ($page_ids as $p_num => $p_id) {
            $stmt->bindValue(':p' . $p_num, $p_id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $data === false ? null : $data;
    }

    /**
     * Remove revision by ID
     *
     * @param int $revision_id
     * @return bool
     */
    public function delete(int $revision_id): bool {
        $stmt = $this->database->prepare("
        DELETE " . self::TABLE . ", " . Data::TABLE . "
        FROM " . self::TABLE . ", " . Data::TABLE . "
        WHERE " . self::TABLE . ".id = :revision_id
        AND " . Data::TABLE . ".revisions_id = " . self::TABLE . ".id
        ");
        $stmt->bindValue(':revision_id', $revision_id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

}
