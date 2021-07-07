<?php

namespace VersionControl;

use \VersionControl\i18n;

use \ProcessWire\Inputfield;
use \ProcessWire\InputfieldWrapper;
use \ProcessWire\Page;
use \ProcessWire\ProcessVersionControl;

/**
 * Version Control Markup Helper
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class MarkupHelper extends \ProcessWire\Wire {

    /**
     * Get the 'history' tab for the Page Edit form
     *
     * @param array $data
     * @param Page $page
     * @return InputfieldWrapper
     */
    public function getHistoryTab(array $data, Page $page): InputfieldWrapper {

        // Render markup for a pager.
        $pager = "";
        if ($data['total'] > $data['limit']) {
            $pager_links = 20;
            $pager_page = (int) $data['start']/$data['limit']+1;
            $pager_pages = ceil($data['total']/$data['limit']);
            $pager = $this->renderPager($pager_links, $pager_page, $pager_pages);
        }

        // Inputfield element for the history tab.
        $tab = new InputfieldWrapper;
        $tab->attr('id', 'VersionControlHistory');
        $tab->attr('title', i18n::getText('History')); // Tab Label: History
        if (isset($this->input->get->users_id) || isset($this->input->get->page)) {
            $tab->attr('data-active', true);
        }

        // Define the date format.
        $processModule = $this->modules->get('ProcessVersionControl');
        $defaults = ProcessVersionControl::$defaultData;
        $date_format = $processModule->date_format != $defaults['date_format'] ? $processModule->date_format : null;

        // Rendering templates.
        $templates = [
            'button' => '<a class="history-tab__button history-tab__button--%s"%s href="%s">'
                      . '<i class="fa fa-%s" title="%5$s" aria-hidden="true"></i>'
                      . '<span class="version-control--visually-hidden">%5$s</span>'
                      . '</a>',
        ];

        // Setup a datatable and append it to the history tab.
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->headerRow([
            i18n::getText('Revision'),
            i18n::getText('Author'),
            i18n::getText('Changes'),
            i18n::getText('Timestamp'),
            i18n::getText('Comment'),
            '', // Placeholder
        ]);
        $process_id = $this->modules->getModuleID('ProcessVersionControl');
        $process_page = $this->wire('pages')->get('template=admin, process=' . $process_id)->url;
        $comment = vsprintf($templates['button'], [
            'edit-comment',
            ' data-revision="%d"',
            '#',
            'edit',
            i18n::getText('Edit comment'),
        ]);
        $restore = vsprintf($templates['button'], [
            'restore',
            '',
            $process_page . 'restore/?pages_id=' . $page->id . '&revision=%d',
            'undo',
            i18n::getText('Restore revision'),
        ]);
        $preview = vsprintf($templates['button'], [
            'preview',
            ' data-date="%s"',
            $process_page . 'preview/?pages_id=' . $page->id . '&revision=%d',
            'eye',
            i18n::getText('Preview revision'),
        ]);
        $counter = 0;
        foreach ($data['data'] as $row) {
            unset($row['users_id']);
            if ($date_format) $row['timestamp'] = date($date_format, strtotime($row['timestamp']));
            $toggle_preview = $page->viewable() ? sprintf($preview, $row['timestamp'], $row['id']) : '';
            $toggle_restore = $page->editable() ? sprintf($restore, $row['id']) : '';
            $row['buttons'] = sprintf($comment, $row['id']) . $toggle_preview . ($data['start'] || $counter ? $toggle_restore : '<span></span>');
            $table->row(array_values($row));
            ++$counter;
        }
        $field = $this->modules->get('InputfieldMarkup');
        $table = $table->render() . $pager;
        if (empty($table)) {
            $field->value = i18n::getText('No history of changes found.');
        } else {
            $field->value = $table;
        }
        $field->label = i18n::getText('History');
        $tab->append($field);

        // Add filters.
        if (count($data['data']) > 1) {

            $fieldset = $this->modules->get('InputfieldFieldset');
            $fieldset->attr('id+name', 'history_filters');
            $fieldset->label = i18n::getText('Filters');
            $fieldset->icon = 'filter';
            if (!$this->input->get->users_id) {
                $fieldset->collapsed = Inputfield::collapsedYes;
            }
            $tab->prepend($fieldset);

            $field = $this->modules->get('InputfieldHidden');
            $field->name = 'id';
            $field->value = $page->id;
            $fieldset->add($field);

            $field = $this->modules->get('InputfieldSelect');
            $field->attr('id+name', 'users_id');
            $field->addOption('', i18n::getText('All'));
            $field->addOptions($this->modules->get('VersionControl')->getUsersCache($page));
            $field->value = $this->input->get->users_id;
            $field->label = i18n::getText('Filter by Author');
            $field->description = i18n::getText('When selected, only revisions authored by specific user will be shown.');
            if (!$this->input->get->users_id) {
                $field->collapsed = Inputfield::collapsedYes;
            }
            $fieldset->add($field);
        }

        return $tab;
    }

    /**
     * Render markup for a pager
     *
     * @param int $links Number of pager links visible at once.
     * @param int $page Identifies currently active pager item.
     * @param int $pages Number of total pager items available.
     * @return string Rendered pager, or empty string if pager couldn't be rendered.
     */
    protected function renderPager(int $links, int $page, int $pages): string {

        if ($pages < 2) {
            return '';
        }

        // Convert GET params to string.
        $get = "";
        foreach ($this->input->get as $key => $value) {
            if ($key != 'page' && $value != '') {
                $get .= '&amp;' . urlencode($key) . '=' . urlencode($value);
            }
        }

        // Calculate start and end points.
        $start = 1;
        $end = $pages;
        if ($end > $links) {
            $start = (int) $page - $links / 2;
            if ($start < 1) $start = 1;
            $end = $start + ($links - 1);
            if ($end > $pages) $end = $pages;
            if ($end - $page < (int) $links / 2 - 1) {
                $start -= ((int) $links / 2) - ($end - $page);
                if ($start < 1) $start = 1;
            }
        }

        // Generate markup.
        $out = '<ul class="MarkupPagerNav MarkupPagerNavCustom">';
        if ($start > 1) {
            $out .= '<li><a href="./?page=1' . $get . '"><span>1</span></a></li>';
            if ($start > 2) {
                $out .= '<li class="MarkupPagerNavSeparator">&hellip;</li>';
            }
        }
        for ($i = $start; $i <= $pages; ++$i) {
            $here = ($page == $i) ? ' class="MarkupPagerNavOn"' : '';
            $out .= '<li' . $here . '><a href="./?page=' . $i . $get . '"><span>' . $i . '</span></a></li>';
            if ($pages > $links && $i == $end && $i < $pages) {
                if ($pages - $i > 1) {
                    $out .= '<li class="MarkupPagerNavSeparator">&hellip;</li>';
                }
                $i = $pages - 1;
                if ($i < $end) $i = $end + 1;
            }
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Render markup for field revisions
     *
     * @param array $data
     * @param Page $page
     * @return string
     */
    public function renderFieldRevisions(array $data, Page $page): string {

        // Translatable strings.
        $strings = [
            'restore' => i18n::getText('Restore'),
            'compare' => i18n::getText('Compare'),
            'label_for_current_revision' => i18n::getText('Current revision'),
            'label_for_revisions' => i18n::getText('Stored revisions for field %s'),
        ];

        // Rendering templates.
        $templates = [
            'outer_container' => '<div id="version-control-data">%s</div>',
            'field_container' => '<div class="field-revisions%s%4$s" id="field-revisions--%2$s" data-field="%2$s" data-revision="%3$s" tabindex=-1 aria-hidden="true" aria-label="%4$s">'
                               . '<div>%6$s</div>'
                               . '</div>',
            'table_container' => '<table>%s</table>',
            'table_head' => '<thead><tr><th></th><th>%s</th><th>%s</th></tr></thead>',
            'table_body' => '<tbody>%s</tbody>',
            'table_item' => '<tr class="field-revision" data-revision="%1$s" data-date="%2$s">'
                          . '<td class="field-revision__current">'
                          . '<span class="field-revision__current-icon" aria-hidden="true"></span>'
                          . '<span class="field-revision__current-label version-control--visually-hidden" aria-hidden="true">' . $strings['label_for_current_revision'] . '</span>'
                          . '</td>'
                          . '<td class="field-revision__date">%2$s</td>'
                          . '<td class="field-revision__user">%3$s</td>'
                          . '<td>%4$s%5$s</td>'
                          . '</tr>',
            'button' => '<button class="field-revision__button field-revision__button--%s" title="%2$s">'
                      . '<span class="version-control--visually-hidden-if-scrollable">%2$s</span>'
                      . '</button>',
        ];

        // Action buttons.
        $buttons = [
            'restore' => sprintf($templates['button'], 'restore', $strings['restore']),
            'compare' => sprintf($templates['button'], 'diff', $strings['compare']),
        ];

        // Restorable fields.
        $restore_enabled = [];

        $dataset = [];
        foreach ($data as $field => $field_data) {

            // Check if diff can and should be enabled, and try to read revision from field data.
            $has_diff = !$this->diff_disabled;
            if ($has_diff) {
                $field_name = strpos($field, '_repeater') ? preg_replace('/_repeater[0-9]+$/', '', $field) : $field;
                $field_object = $this->wire('fields')->get($field_name);
                $has_diff = $field_object !== null && $field_object->type != 'FieldtypeFile';
            }
            $revision = !empty($field_data) ? $field_data[0]['revision'] : '';

            // Check if restore button should be displayed.
            if (!isset($restore_enabled[$field])) {
                $has_restore = true;
                $field_object = $this->wire('fields')->get($field);
                if ($field_object && $field_object->id) {
                    $inputfield = $field_object->getInputfield($page);
                    if (in_array($inputfield->collapsed, [7, 8])) {
                        $has_restore = false;
                    }
                }
                $restore_enabled[$field] = $has_restore;
            }
            $has_restore = $restore_enabled[$field];

            $table = [];
            if (!empty($field_data)) {

                // Data table heading row.
                $table['head'] = sprintf(
                    $templates['table_head'],
                    i18n::getText('Timestamp'),
                    i18n::getText('Author')
                );

                $table['body'] = [];
                foreach ($field_data as $row) {

                    // Get user name for this row of data.
                    $user_name = "";
                    if ($this->user_name_format && $this->user_name_format !== "{name}") {
                        $user = $this->wire('users')->get((int) $row['users_id']);
                        if ($user->id) {
                            $user_name = wirePopulateStringTags($this->user_name_format, $user);
                        }
                    }
                    $user_name = $user_name ?: $row['username'];

                    // Markup for a single data row.
                    $table['body'][] = sprintf(
                        $templates['table_item'],
                        $row['revision'],
                        $row['date'],
                        $user_name,
                        $has_restore ? $buttons['restore'] : '',
                        $has_diff ? $buttons['compare'] : ''
                    );
                }
                $table['body'] = sprintf($templates['table_body'], implode($table['body']));
            }
            $dataset[] = sprintf(
                $templates['field_container'],
                $has_diff ? ' field-revisions--diff' : '',
                $field,
                $revision,
                $has_restore ? ' field-revisions--restore' : '',
                sprintf($strings['label_for_revisions'], $field),
                sprintf($templates['table_container'], implode($table))
            );
        }

        return sprintf(
            $templates['outer_container'],
            !empty($dataset) ? implode($dataset) : htmlspecialchars(
                i18n::getText('There are no earlier versions of this field available')
            )
        );
    }

}
