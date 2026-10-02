<?php
// This file is part of Moodle - http://moodle.org/
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
 * Post-install steps for mod_jupyter.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Register the .ipynb file type so the file picker accepts notebooks.
 */
function xmldb_jupyter_install() {
    $types = core_filetypes::get_types();
    if (!isset($types['ipynb'])) {
        core_filetypes::add_type('ipynb', 'application/x-ipynb+json', 'text', [], '', 'Jupyter notebook');
    }
    return true;
}
