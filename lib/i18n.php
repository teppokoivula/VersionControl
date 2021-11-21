<?php

namespace VersionControl;

use function \ProcessWire\__;
use function \ProcessWire\_n;

/**
 * Version Control internationalization (i18n) helper
 *
 * This class is a wrapper for ProcessWire's built-in string translation features. Primary goal here is to group all
 * GUI translations into one place, and avoid issues in case code needs to be rearranged: translations are tied to a
 * specific file, which means that they get lost if that file is moved or renamed.
 *
 * Getting an updated list of translations from source code:
 *
 * ```
 * egrep i18n::getText\\\(.* . -R -o -h | sed 's/)\+[;,$]/),/' | sed 's/)$/),/' | sed 's/i18n::getText/__/' | sed "s/__""('\(.*\?\)', '\(.*\?\)', .*\?)/\[\n    _n""('\1', '\2', 1),\n    _n""('\1', '\2', 2),\n\]/g" | awk '/^(\[|\],)$/ || !x[$0]++'
 * egrep i18n::get\\\(.* . -R -o -h | sed 's/)\+[;,$]/),/' | sed 's/)$/),/' | sed "s/i18n""::get('\(.*\?\)')/'\1' => __""('\1')/"
 * ```
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class i18n {

    /**
     * Translation cache
     *
     * @var array|null
     */
    protected static $translations = null;

    /**
     * Get all translations
     *
     * @return array
     */
    public static function getAll(): array {
        return static::$translations ?? [
            __('Removed directory: %s'),
            __('History'), // Tab Label: History
            __('Revision'),
            __('Author'),
            __('Changes'),
            __('Timestamp'),
            __('Comment'),
            __('Edit comment'),
            __('Restore revision'),
            __('Preview revision'),
            __('No history of changes found.'),
            __('History'),
            __('Filters'),
            __('All'),
            __('Filter by Author'),
            __('When selected, only revisions authored by specific user will be shown.'),
            __('Restore'),
            __('Compare'),
            __('Current revision'),
            __('Stored revisions for field %s'),
            __('There are no earlier versions of this field available'),
            __('Table %s already exists'),
            __('Created table: %s'),
            __('Dropped table: %s'),
            __('Imported existing Version Control For Text Fields data'),
            __('Unrecognized path'),
            __('Page reverted to revision #%d'),
            __('Page doesn\'t exist: %d'),
            __('Permission denied (Page not viewable)'),
            __('Permission denied (Page not editable)'),
            __('Revision doesn\'t exist: %d'),
            __('Compare with current'),
            __('Editing this data is currently disabled. Restore it by saving the page or switch to current version first.'),
            __('Type in comment text for this revision (max 255 characters)'),
            __('This is the page as it appeared on %s. Click here to close the preview.'),
            __('Are you sure that you want to revert this page to an earlier revision?'),
            __('Show side by side'),
            __('Show as a list'),
            __('There is no difference between these revisions.'),
            __('Toggle a list of revisions'),
            [
                _n('Populated data for %d page using template %s', 'Populated data for %d pages using template %s', 1),
                _n('Populated data for %d page using template %s', 'Populated data for %d pages using template %s', 2),
            ],
            [
                _n('Removed stored data for %d page using template %s', 'Removed stored data for %d pages using template %s', 1),
                _n('Removed stored data for %d page using template %s', 'Removed stored data for %d pages using template %s', 2),
            ],
            __('Removed stored data for field %s'),
        ];
    }

    /**
     * Get single translation by key
     *
     * @param string $key
     * @param int $count
     * @return string|null
     */
    public static function get(string $key, int $count = 1): ?string {
        if (static::$translations === null) {
            static::$translations = static::getAll();
        }
        $text = static::$translations[$key] ?? null;
        return is_array($text) ? $text[$count > 1 ? 1 : 0] : $text;
    }

    /**
     * Get single translation by source text
     *
     * This method can be used to access both singular and plural translations. If a string has both versions, provide
     * singular version as the first argument, plural as the second one, and the quantity as the third argument. For
     * strings that only have one version, you just need to provide the first argument.
     *
     * @param string $text
     * @param string|null $text_plural
     * @param int $count
     * @return string
     */
    public static function getText(string $text, ?string $text_plural = null, int $count = 1): string {
        if ($text_plural !== null) {
            return _n($text, $text_plural, $count, __FILE__);
        }
        return __($text, __FILE__);
    }
}
