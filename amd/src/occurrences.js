// This file is part of the Zoom plugin for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Occurrences table helpers (occurrence-first scheduling).
 *
 * - Weekday label next to each editable date input stays in step while the
 *   date is changed ("is this really a Monday?" at a glance).
 * - Per-row dirty state: Save is grey and disabled while the row matches
 *   the stored schedule, turns primary (blue) when there is something to
 *   save; a Revert button appears alongside to discard the edit.
 * - The collapsed add-occurrence form can be discarded (close button).
 *
 * @module     mod_zoom/occurrences
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function() {
            var lang = document.documentElement.lang || undefined;

            document.querySelectorAll('input[type=date][name=newdate]').forEach(function(input) {
                var span = input.parentElement.querySelector('[data-zoom-occ-weekday]');
                if (!span) {
                    return;
                }
                input.addEventListener('input', function() {
                    if (!input.value) {
                        span.textContent = '';
                        return;
                    }
                    var day = new Date(input.value + 'T12:00:00');
                    span.textContent = day.toLocaleDateString(lang, {weekday: 'short'});
                });
            });

            document.querySelectorAll('form[id^=zoom-occ-move-]').forEach(function(form) {
                var fields = Array.prototype.filter.call(form.elements, function(el) {
                    return ['newdate', 'newtime', 'newduration'].indexOf(el.name) !== -1;
                });
                var save = form.querySelector('input[type=submit]');
                var revert = form.querySelector('[data-zoom-occ-revert]');
                if (!fields.length || !save) {
                    return;
                }
                var initial = fields.map(function(el) {
                    return el.value;
                });
                var refresh = function() {
                    var dirty = fields.some(function(el, i) {
                        return el.value !== initial[i];
                    });
                    save.disabled = !dirty;
                    save.classList.toggle('btn-primary', dirty);
                    save.classList.toggle('btn-secondary', !dirty);
                    if (revert) {
                        revert.classList.toggle('d-none', !dirty);
                    }
                };
                fields.forEach(function(el) {
                    el.addEventListener('input', refresh);
                    el.addEventListener('change', refresh);
                });
                if (revert) {
                    revert.addEventListener('click', function(e) {
                        e.preventDefault();
                        fields.forEach(function(el, i) {
                            el.value = initial[i];
                            el.dispatchEvent(new Event('input'));
                        });
                        refresh();
                    });
                }
                refresh();
            });

            document.querySelectorAll('[data-zoom-occ-close]').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var details = button.closest('details');
                    if (details) {
                        details.removeAttribute('open');
                    }
                });
            });
        }
    };
});
