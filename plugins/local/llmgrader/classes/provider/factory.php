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

namespace local_llmgrader\provider;

/**
 * Finds the configured provider.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factory {
    /**
     * Providers available: short name => class (every class in this namespace implementing provider).
     *
     * @return string[]
     */
    public static function available(): array {
        $providers = [];
        foreach (array_keys(\core_component::get_component_classes_in_namespace('local_llmgrader', 'provider')) as $class) {
            $class = ltrim($class, '\\');
            if (is_subclass_of($class, provider::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $providers[substr($class, strrpos($class, '\\') + 1)] = $class;
            }
        }
        ksort($providers);
        return $providers;
    }

    /**
     * The provider chosen in the settings (default: OpenAI-compatible).
     *
     * @return provider
     */
    public static function get(): provider {
        $available = self::available();
        $name = get_config('local_llmgrader', 'provider') ?: 'openai_compatible';
        $class = $available[$name] ?? $available['openai_compatible'];
        return new $class();
    }
}
