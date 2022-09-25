<?php

namespace VersionControl\Data;

use VersionControl\DataObject;
use VersionControl\i18n;

use ProcessWire\PagefilesManager;

use function ProcessWire\wireMkdir;
use function ProcessWire\wireRmdir;
use function ProcessWire\wireCopy;

/**
 * Version Control Files
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class Files extends DataObject {

    /**
     * Name of the databae table containing metadata for stored files
     *
     * Having file metadata stored in the database makes tasks such as mime type checking and fetching file sizes and
     * file hashes fast. Another benefit is easier and more efficient cleanup for orphaned files.
     *
     * @var string
     */
    const TABLE = DataObject::TABLE_PREFIX . 'files';

    /**
     * Name of the database table connecting file metadata with field values
     *
     * @var string
     */
    const TABLE_DATA_FILES = DataObject::TABLE_PREFIX . 'data_files';

    /**
     * Use fileinfo extension?
     *
     * @var bool|null
     */
    protected static $use_fileinfo = null;

    /**
     * Use fileinfo extension?
     *
     * @var bool|null
     */
    protected static $use_mime_content_type = null;

    /**
     * Path for stored files
     *
     * @var string|null
     */
    protected static $path = null;

    /**
     * URL for stored files
     *
     * @var string|null
     */
    protected static $url = null;

    /**
     * Set up data structures
     */
    public function install() {
        $this->createTable(self::TABLE, [
            'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'filename VARCHAR(255) NOT NULL',
            'mime_type VARCHAR(255)',
            'size INT UNSIGNED NOT NULL',
        ]);
        $this->createTable(self::TABLE_DATA_FILES, [
            'data_id INT UNSIGNED NOT NULL',
            'files_id INT UNSIGNED NOT NULL',
            'PRIMARY KEY (data_id, files_id)',
        ]);
    }

    /**
     * Tear down data structures
     */
    public function uninstall() {
        $this->dropTable(self::TABLE);
        $this->dropTable(self::TABLE_DATA_FILES);
        $path = $this->getPath();
        if (is_dir($path)) {
            wireRmdir($path, true);
            $this->message(sprintf(
                i18n::getText('Removed directory: %s'),
                $path
            ));
        }
    }

    /**
     * Add new file
     *
     * Note that if the file already exist, this method will return the database ID for the existing file.
     *
     * @param string $filename
     * @param string $source_filename Optional source file
     * @param string $data_id Optional data table row ID
     * @return int|null ID for stored file, or null in case of an failure
     */
    public function add(string $filename, ?string $source_filename = null, ?int $data_id = null): ?int {

        // if source file was not provided, assume first argument to contain source file
        if ($source_filename === null) {
            $source_filename = $filename;
            $filename = hash_file('sha1', $filename) . "." . basename($filename);
        }

        // bail out early if source filename is empty or source file doesn't exist
        if (empty($source_filename) || !is_file($source_filename)) {
            return null;
        }

        // define destination for stored file data
        $dir = $this->getPath() . substr($filename, 0, 2) . "/";
        $file = $dir . $filename;

        // if this is an existing file, attempt to fetch an ID from the database
        if (is_file($file)) {
            $file_id = $this->getID($filename);
            if ($file_id !== null) {
                return $file_id;
            }
        }

        // make sure that directory for files exists, bail out early if it doesn't exist and can't be created
        // note: we're intentionally creating the variations directory here, since it may be needed later on
        if (!is_dir($dir) && !wireMkdir($dir . 'variations', true)) {
            return null;
        }

        // attempt to copy the source file to target location, bail out early if copy operation fails
        if (!wireCopy($source_filename, $file)) {
            return null;
        }

        // insert database row for the file and return file ID on success
        $stmt = $this->database->prepare("INSERT INTO " . self::TABLE . " (filename, mime_type, size) VALUES (:filename, :mime_type, :size)");
        $stmt->bindValue(':filename', $filename, \PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', $this->getMimeType($file), \PDO::PARAM_STR);
        $stmt->bindValue(':size', filesize($file) ?: 0, \PDO::PARAM_INT);
        $file_id = $stmt->execute() ? $this->database->lastInsertId() : null;

        // if file was successfully and data ID was provided, join the two
        if ($file_id !== null && $data_id !== null) {
            $stmt = $this->database->prepare("INSERT INTO " . self::TABLE_DATA_FILES . " (data_id, files_id) VALUES (:data_id, :file_id)");
            $stmt->bindValue(':data_id', $data_id, \PDO::PARAM_INT);
            $stmt->bindValue(':file_id', $file_id, \PDO::PARAM_INT);
        }

        return $file_id;
    }

    /**
     * Get data for stored file
     *
     * @param int $file_id
     * @return array|null
     */
    public function getData(int $file_id, array $keys = []): ?array {

        // filter and validate keys array
        $allowed_keys = [
            'id',
            'filename',
            'mime_type',
            'size',
        ];
        if (empty($keys)) {
            $keys = $allowed_keys;
        } else {
            $keys = array_filter($keys, function($key) {
                return in_array($key, $allowed_keys);
            });
            if (empty($keys)) {
                return null;
            }
        }

        // fetch and return data
        $stmt = $this->database->prepare("SELECT " . implode(", ", $keys) . " FROM " . self::TABLE . " WHERE id = :file_id");
        $stmt->bindValue(':file_id', $file_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result === false ? null : $result['id'];
    }

    /**
     * Remove data for provided file
     *
     * @param int $files_id
     * @return bool
     */
    public function delete(int $files_id): bool {
        $stmt = $this->database->prepare("
        DELETE " . self::TABLE . ", " . self::TABLE_DATA_FILES . "
        FROM " . self::TABLE . ", " . self::TABLE_DATA_FILES . "
        WHERE " . self::TABLE . ".id = :files_id
        AND " . self::TABLE_DATA_FILES . ".files_id = :files_id
        ");
        $stmt->bindValue(':files_id', $files_id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get path for stored files
     *
     * @return string|null
     */
    public function getPath(): ?string {
        if (!self::$path) {
            $this->setupFileStore();
        }
        return self::$path;
    }

    /**
     * Get URL for stored files
     *
     * @return string|null
     */
    public function getURL(): ?string {
        if (!self::$url) {
            $this->setupFileStore();
        }
        return self::$url;
    }

    /**
     * Setup file storage
     *
     * @throws WireException if files directory doesn't exist and creating it fails
     */
    protected function setupFileStore() {
        if (self::$path) return;
        $process_module_id = $this->wire('modules')->getModuleID('ProcessVersionControl');
        if ($process_module_id) {
            $process_page = $this->wire('pages')->get('template=admin, process=' . $process_module_id);
            if ($process_page->id) {
                $filemanager = $this->wire(new PagefilesManager($process_page));
                self::$path = $filemanager->path();
                self::$url = $filemanager->url();
                if (!is_dir(self::$path) && !wireMkdir(self::$path)) {
                    throw new WireException(sprintf(
                        'Creating directory failed: %s',
                        self::$path
                    ));
                }
            }
        }
    }

    /**
     * Get ID of a stored file by filename
     *
     * @param string $filename
     * @return int|null
     */
    protected function getID(string $filename): ?int {
        $stmt = $this->database->prepare("SELECT id FROM " . self::TABLE . " WHERE filename = :filename");
        $stmt->bindValue(':filename', $filename, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result === false ? null : $result['id'];
    }

    /**
     * Get mime content type for file
     *
     * @param string $filename
     * @return string
     */
    protected function getMimeType(string $filename): string {

        // PECL fileinfo extension is not enabled by default in Windows, so check for that first
        if (self::$use_fileinfo || self::$use_fileinfo === null || extension_loaded('fileinfo') && function_exists('finfo_open')) {
            self::$use_fileinfo = true;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mime_type ?: '';
        }
        self::$use_fileinfo = false;

        // check if mime_content_type function is available
        if (self::$use_fileinfo || self::$use_fileinfo === null || function_exists('mime_content_type')) {
            return mime_content_type($filename) ?: '';
        }
        self::$use_fileinfo = false;

        return '';
    }

}
