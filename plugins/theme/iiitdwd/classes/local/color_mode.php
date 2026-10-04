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

namespace theme_iiitdwd\local;

/**
 * Day/Night colour mode behind <html data-theme>.
 *
 * Logged-in users' choice is stored in the user preference below (saved by amd/src/theme_toggle.js through the
 * core_user preferences API), so it follows them across browsers and sessions. theme_toggle.js also keeps a
 * cookie of the same name, which the server uses when there is no saved preference (logged out, the login page,
 * a user who never toggled). A cookie rather than localStorage: Moodle clears localStorage at every login and
 * cache purge (core/storage_validation), and the server cannot read it, so the page would flash the default.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class color_mode {
    /** @var string User preference, cookie and localStorage key holding the mode. */
    const PREFERENCE = 'theme_iiitdwd_colormode';

    /** @var string Night mode: the theme's original palette, and the default. */
    const DARK = 'dark';

    /** @var string Day mode. */
    const LIGHT = 'light';

    /**
     * The logged-in user's saved mode.
     *
     * @return string|null dark or light, or null when logged out, a guest, or nothing is saved.
     */
    public static function saved(): ?string {
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $mode = get_user_preferences(self::PREFERENCE);
        return in_array($mode, [self::DARK, self::LIGHT], true) ? $mode : null;
    }

    /**
     * The mode this browser last used (cookie set by theme_toggle.js).
     *
     * @return string|null dark or light, or null if none.
     */
    public static function from_cookie(): ?string {
        $mode = $_COOKIE[self::PREFERENCE] ?? null;
        return in_array($mode, [self::DARK, self::LIGHT], true) ? $mode : null;
    }

    /**
     * The mode to render the page in: the saved preference, else this browser's last choice, else Night.
     *
     * @return string dark or light
     */
    public static function current(): string {
        return self::saved() ?? self::from_cookie() ?? self::DARK;
    }
}
