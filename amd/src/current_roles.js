// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Shows the selected user's current roles under the user picker.
 *
 * @module     local_timedroles/current_roles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

/**
 * Set up the panel.
 *
 * @param {string} selectId id of the user select (the autocomplete's underlying select)
 * @param {string} panelId id of the panel holding the role data
 */
export const init = (selectId, panelId) => {
    const select = document.getElementById(selectId);
    const panel = document.getElementById(panelId);
    if (!select || !panel) {
        return;
    }

    let roles = {};
    try {
        roles = JSON.parse(panel.dataset.roles || '{}');
    } catch (e) {
        return;
    }

    const update = () => {
        const userid = select.value;
        panel.textContent = '';
        if (!userid) {
            panel.textContent = panel.dataset.strSelect;
            return;
        }
        const list = roles[userid] || [];
        if (!list.length) {
            panel.textContent = panel.dataset.strNone;
            return;
        }
        const ul = document.createElement('ul');
        ul.className = 'list-unstyled mb-0';
        list.forEach((name) => {
            const li = document.createElement('li');
            li.textContent = name;
            ul.appendChild(li);
        });
        panel.appendChild(ul);
    };

    // The autocomplete fires change through jQuery, so listen the same way.
    $(select).on('change', update);
    update();
};
