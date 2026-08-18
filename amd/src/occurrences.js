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
 * - One row edits at a time: Edit swaps the row's text for its inputs and
 *   locks the other rows' Edit buttons, so a Save can never discard
 *   half-done edits elsewhere. Revert restores the row and unlocks.
 * - Per-row dirty state: Save is grey and disabled while the row matches
 *   the stored schedule, turns primary (blue) when there is something to
 *   save.
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
                var scope = input.closest('.input-group') || input.parentElement;
                var span = scope.querySelector('[data-zoom-occ-weekday]');
                var text = scope.querySelector('.zoom-occ-datetext');
                var btn = scope.querySelector('.zoom-occ-datebtn');
                // Locale-proper via Intl, keyed to the MOODLE language (the
                // page lang attribute): formatToParts teaches us the field
                // order and separator, so typing parses with the same rules
                // the display uses.
                var dtf = new Intl.DateTimeFormat(lang, {day: '2-digit', month: '2-digit', year: 'numeric'});
                var order = [];
                dtf.formatToParts(new Date(2001, 10, 22)).forEach(function(part) {
                    if (part.type === 'day' || part.type === 'month' || part.type === 'year') {
                        order.push(part.type);
                    }
                });
                var fmt = function(iso) {
                    return iso ? dtf.format(new Date(iso + 'T12:00:00')) : '';
                };
                var refresh = function() {
                    if (span) {
                        span.textContent = input.value
                            ? new Date(input.value + 'T12:00:00').toLocaleDateString(lang, {weekday: 'short'})
                            : '';
                    }
                    if (text) {
                        text.value = fmt(input.value);
                        text.classList.remove('is-invalid');
                    }
                };
                input.addEventListener('input', refresh);

                // Deterministic day/month/year display: the text field becomes
                // the visible control (a native date input renders in the
                // BROWSER locale — MM/DD/YYYY on an English browser), the
                // native input shrinks to an invisible value carrier whose
                // picker opens from the calendar button or the weekday label.
                if (text && btn) {
                    input.style.position = 'absolute';
                    input.style.opacity = '0';
                    input.style.width = '1px';
                    input.style.height = '1px';
                    input.style.padding = '0';
                    input.style.border = '0';
                    input.tabIndex = -1;
                    text.classList.remove('d-none');
                    btn.classList.remove('d-none');
                    var openPicker = function(e) {
                        e.preventDefault();
                        try {
                            if (input.showPicker) {
                                input.showPicker();
                            } else {
                                input.click();
                            }
                        } catch (err) {
                            input.click();
                        }
                    };
                    btn.addEventListener('click', openPicker);
                    if (span) {
                        span.style.cursor = 'pointer';
                        span.addEventListener('click', openPicker);
                    }
                    text.placeholder = fmt('1999-12-31');
                    text.addEventListener('change', function() {
                        var raw = text.value.trim();
                        if (raw === '') {
                            input.value = '';
                        } else {
                            var bits = raw.split(/[^0-9]+/).filter(Boolean);
                            var fields = {};
                            order.forEach(function(type, i) {
                                fields[type] = parseInt(bits[i], 10);
                            });
                            if (bits.length !== 3 || String(fields.year).length > 4 || fields.year < 1000
                                    || !(fields.month >= 1 && fields.month <= 12)
                                    || !(fields.day >= 1 && fields.day <= 31)) {
                                text.classList.add('is-invalid');
                                return;
                            }
                            input.value = fields.year + '-' + ('0' + fields.month).slice(-2)
                                + '-' + ('0' + fields.day).slice(-2);
                        }
                        input.dispatchEvent(new Event('input'));
                        input.dispatchEvent(new Event('change'));
                    });
                }
                refresh();
            });

            var rowEntries = [];
            var setRowActive = function(entry, active) {
                entry.row.querySelectorAll('.zoom-occ-view').forEach(function(el) {
                    el.classList.toggle('d-none', active);
                });
                entry.row.querySelectorAll('.zoom-occ-edit').forEach(function(el) {
                    el.classList.toggle('d-none', !active);
                });
                entry.editBtn.classList.toggle('d-none', active);
                // The lock: while one row edits, no other row can start.
                rowEntries.forEach(function(other) {
                    if (other !== entry) {
                        other.editBtn.disabled = active;
                    }
                });
            };
            document.querySelectorAll('form[id^=zoom-occ-move-]').forEach(function(form) {
                var fields = Array.prototype.filter.call(form.elements, function(el) {
                    return ['newdate', 'newtime', 'newduration'].indexOf(el.name) !== -1;
                });
                var save = form.querySelector('input[type=submit]');
                var revert = form.querySelector('[data-zoom-occ-revert]');
                var row = form.closest('tr');
                var editBtn = row && row.querySelector('[data-zoom-occ-editrow]');
                if (!fields.length || !save || !editBtn) {
                    return;
                }
                var initial = fields.map(function(el) {
                    return el.value;
                });
                var entry = {row: row, editBtn: editBtn};
                rowEntries.push(entry);
                var refresh = function() {
                    var dirty = fields.some(function(el, i) {
                        return el.value !== initial[i];
                    });
                    save.disabled = !dirty;
                    save.classList.toggle('btn-primary', dirty);
                    save.classList.toggle('btn-secondary', !dirty);
                };
                fields.forEach(function(el) {
                    el.addEventListener('input', refresh);
                    el.addEventListener('change', refresh);
                });
                editBtn.addEventListener('click', function() {
                    setRowActive(entry, true);
                });
                if (revert) {
                    revert.addEventListener('click', function(e) {
                        e.preventDefault();
                        fields.forEach(function(el, i) {
                            el.value = initial[i];
                            el.dispatchEvent(new Event('input'));
                        });
                        refresh();
                        setRowActive(entry, false);
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
                                    // "+5m" = every 4 weeks: the weekday is
                                    // preserved (calendar months would drift).
                                    d.setDate(d.getDate() + 28 * added);
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
