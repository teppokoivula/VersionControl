var enableDiffSwitch = function(moduleConfig) {
    var diff_table;
    var diff_div = document.getElementById('diff');
    var diff_switch = document.createElement('a');
    diff_switch.className = 'diff-switch';
    diff_switch.text = moduleConfig.i18n.diffSideBySide;
    diff_switch.href = '#';
    diff_switch.addEventListener('click', function(event) {
        event.preventDefault();
        if (!document.getElementById('diff-table')) {
            diff_table = document.createElement('table');
            diff_table.id = 'diff-table';
            var previous_tag_name, diff_row;
            Array.prototype.forEach.call(diff_div.children[0].children, function(item, i) {
                if (!diff_row || diff_row.children.length == 2 || item.children[0].tagName == "SPAN") {
                    if (diff_row && diff_row.children.length == 1) {
                        if (previous_tag_name == "INS") {
                            diff_row.insertBefore(document.createElement('td'), diff_row.children[0]);
                        } else {
                            diff_row.appendChild(document.createElement('td'));
                        }
                    }
                    diff_row = document.createElement('tr');
                    diff_table.appendChild(diff_row);
                    previous_tag_name = "";
                }
                if (!previous_tag_name || item.children[0].tagName == "SPAN" || (item.children[0].tagName == "DEL" && previous_tag_name == "INS") || (item.children[0].tagName == "INS" && previous_tag_name == "DEL")) {
                    var diff_col = document.createElement('td');
                    diff_col.innerHTML = item.innerHTML;
                    diff_col.className = 'diff-col-' + item.children[0].tagName.toLowerCase();
                    diff_row.appendChild(diff_col);
                    if (item.children[0].tagName == "SPAN") {
                        diff_row.appendChild(diff_col.cloneNode(true));
                    }
                } else if (previous_tag_name == item.children[0].tagName) {
                    if (previous_tag_name == "INS") {
                        diff_row.insertBefore(document.createElement('td'), diff_row.children[0]);
                    } else {
                        diff_row.appendChild(document.createElement('td'));
                    }
                    var diff_col = document.createElement('td');
                    diff_col.innerHTML = item.innerHTML;
                    diff_col.className = 'diff-col-' + item.children[0].tagName.toLowerCase();
                    diff_row = document.createElement('tr');
                    diff_row.appendChild(diff_col);
                    diff_table.appendChild(diff_row);
                }
                previous_tag_name = item.children[0].tagName;
            });
            if (diff_row && diff_row.children.length == 1) {
                if (previous_tag_name == "INS") {
                    diff_row.insertBefore(document.createElement('td'), diff_row.children[0]);
                } else {
                    diff_row.appendChild(document.createElement('td'));
                }
            }
            diff_div.parentNode.appendChild(diff_table);
            diff_div.style = 'display: none';
            diff_switch.text = moduleConfig.i18n.diffList;
            diff_switch.className = 'diff-switch diff-switch-list';
        } else if (diff_table.style.length) {
            diff_div.style = 'display: none';
            diff_table.style = '';
            diff_switch.text = moduleConfig.i18n.diffList;
            diff_switch.className = 'diff-switch diff-switch-list';
        } else {
            diff_div.style = '';
            diff_table.style = 'display: none';
            diff_switch.text = moduleConfig.i18n.diffSideBySide;
            diff_switch.className = 'diff-switch';
        }
    });
    diff_div.parentNode.insertBefore(diff_switch, diff_div);
}
