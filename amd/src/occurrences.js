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

            document.querySelectorAll('input.zoom-occ-date').forEach(function(input) {
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

            var planner = document.querySelector('[data-zoom-occ-planner]');
            if (planner) {
                var rows = Array.prototype.slice.call(planner.querySelectorAll('[data-zoom-occ-row]'));
                var fieldOf = function(row, name) {
                    return row.querySelector('input[name="zoomplan_' + name + '[]"]');
                };
                var rowValues = function(row) {
                    return {
                        date: fieldOf(row, 'date').value,
                        time: fieldOf(row, 'time').value,
                        minutes: fieldOf(row, 'minutes').value
                    };
                };
                var setRow = function(row, values) {
                    fieldOf(row, 'date').value = values ? values.date : '';
                    fieldOf(row, 'time').value = values ? values.time : '';
                    fieldOf(row, 'minutes').value = values ? values.minutes : '';
                    fieldOf(row, 'date').dispatchEvent(new Event('input'));
                };
                // Sort filled rows by date+time, compact them to the top,
                // hide unused rows, and show ✕ when more than one row.
                var compact = function() {
                    var filled = [];
                    rows.forEach(function(row) {
                        var values = rowValues(row);
                        if (values.date) {
                            filled.push(values);
                        }
                    });
                    filled.sort(function(a, b) {
                        return (a.date + 'T' + a.time) < (b.date + 'T' + b.time) ? -1 : 1;
                    });
                    rows.forEach(function(row, i) {
                        setRow(row, filled[i] || null);
                        row.classList.toggle('d-none', !(i < filled.length || i === filled.length));
                        if (i === filled.length) {
                            row.classList.remove('d-none');
                        }
                        var clear = row.querySelector('[data-zoom-occ-clearrow]');
                        if (clear) {
                            clear.classList.toggle('d-none', !(i < filled.length && filled.length > 1));
                        }
                        row.querySelectorAll('[data-zoom-occ-spread]').forEach(function(button) {
                            button.classList.toggle('d-none', !(i < filled.length));
                        });
                    });
                };
                rows.forEach(function(row) {
                    fieldOf(row, 'date').addEventListener('change', compact);
                    fieldOf(row, 'time').addEventListener('change', compact);
                    var clear = row.querySelector('[data-zoom-occ-clearrow]');
                    if (clear) {
                        clear.addEventListener('click', function(e) {
                            e.preventDefault();
                            setRow(row, null);
                            compact();
                        });
                    }
                });
                var buttons = planner.querySelector('.zoom-occ-planner-buttons');
                if (buttons) {
                    buttons.classList.remove('d-none');
                }
                var lastFilled = function() {
                    var found = null;
                    rows.forEach(function(row) {
                        if (fieldOf(row, 'date').value) {
                            found = row;
                        }
                    });
                    return found;
                };
                var addrow = planner.querySelector('[data-zoom-occ-addrow]');
                if (addrow) {
                    addrow.addEventListener('click', function(e) {
                        e.preventDefault();
                        for (var i = 0; i < rows.length; i++) {
                            if (!fieldOf(rows[i], 'date').value) {
                                rows[i].classList.remove('d-none');
                                fieldOf(rows[i], 'date').focus();
                                return;
                            }
                        }
                    });
                }
                rows.forEach(function(row) {
                    row.querySelectorAll('[data-zoom-occ-spread]').forEach(function(button) {
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            var baseValues = rowValues(row);
                            if (!baseValues.date) {
                                return;
                            }
                            var kind = button.getAttribute('data-zoom-occ-spread');
                            var baseDate = new Date(baseValues.date + 'T12:00:00');
                            var added = 0;
                            var pad = function(n) {
                                return (n < 10 ? '0' : '') + n;
                            };
                            for (var i = 0; i < rows.length && added < 5; i++) {
                                if (fieldOf(rows[i], 'date').value) {
                                    continue;
                                }
                                added++;
                                var d = new Date(baseDate.getTime());
                                if (kind === 'daily') {
                                    d.setDate(d.getDate() + added);
                                } else if (kind === 'weekly') {
                                    d.setDate(d.getDate() + 7 * added);
                                } else {
                                    d.setMonth(d.getMonth() + added);
                                }
                                setRow(rows[i], {
                                    date: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
                                    time: baseValues.time,
                                    minutes: baseValues.minutes
                                });
                            }
                            compact();
                        });
                    });
                });
                compact();
            }

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
