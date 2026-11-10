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

namespace quizaccess_examproctor\local;

/**
 * Recorded webcam video of proctored attempts.
 *
 * The browser records self-contained segments (SEGMENT_SECONDS long, each starting with its own container header) and
 * uploads each one in CHUNK_SECONDS pieces, so leaving the page loses at most one chunk. Chunks are stored in the
 * quiz's module context, file area "video", item id = attempt id, as "<segment>-<seq>.<ext>" (segment = start time in
 * ms); when a segment is complete they are concatenated into "<segment>.<ext>", which plays on its own.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video {

    /** File area holding the videos. */
    const FILEAREA = 'video';

    /** Seconds of video per uploaded chunk. */
    const CHUNK_SECONDS = 10;

    /** Seconds of video per playable segment. */
    const SEGMENT_SECONDS = 60;

    /** Largest chunk accepted (bytes): 10 s at the highest bitrate setting is well below this. */
    const MAX_CHUNK_BYTES = 4 * 1024 * 1024;

    /** Container formats MediaRecorder produces: webm (Chrome, Firefox, Edge, SEB on Windows) and mp4 (Safari). */
    const FORMATS = ['webm' => 'video/webm', 'mp4' => 'video/mp4'];

    /**
     * Video bitrate for the recorder, in bits per second (admin setting, kbps).
     *
     * @return int
     */
    public static function bitrate(): int {
        $kbps = (int) get_config('quizaccess_examproctor', 'videobitrate');
        return max(50, min(1000, $kbps ?: 150)) * 1000;
    }

    /**
     * Stores one uploaded chunk; merges the segment when it is the last one.
     *
     * @param \context_module $context
     * @param int $attemptid
     * @param int $userid The student (owner of the file).
     * @param int $segment Segment start time in ms.
     * @param int $seq Chunk number within the segment, from 0.
     * @param string $format webm or mp4.
     * @param string $path Uploaded temporary file.
     * @param bool $final Whether this chunk ends the segment.
     */
    public static function save_chunk(\context_module $context, int $attemptid, int $userid, int $segment, int $seq,
            string $format, string $path, bool $final): void {
        $fs = get_file_storage();
        $record = [
            'contextid' => $context->id,
            'component' => 'quizaccess_examproctor',
            'filearea' => self::FILEAREA,
            'itemid' => $attemptid,
            'filepath' => '/',
            'filename' => $segment . '-' . sprintf('%04d', $seq) . '.' . $format,
            'userid' => $userid,
            'mimetype' => self::FORMATS[$format],
        ];
        if (!$fs->file_exists($record['contextid'], $record['component'], $record['filearea'], $record['itemid'],
                $record['filepath'], $record['filename'])) {
            $fs->create_file_from_pathname($record, $path);
        }
        if ($final) {
            self::merge_segment($context, $attemptid, $segment);
        }
    }

    /**
     * Concatenates a segment's chunks (after any earlier merged part) into "<segment>.<ext>" and deletes the chunks.
     *
     * @param \context_module $context
     * @param int $attemptid
     * @param int $segment
     */
    public static function merge_segment(\context_module $context, int $attemptid, int $segment): void {
        $parts = [];
        $merged = null;
        foreach (self::area_files($context, $attemptid) as $file) {
            [$seg, $seq, $format] = self::parse_name($file->get_filename());
            if ($seg !== $segment) {
                continue;
            }
            if ($seq === null) {
                $merged = $file;
            } else {
                $parts[$seq] = $file;
            }
        }
        if (!$parts) {
            return;
        }
        ksort($parts);
        $first = reset($parts);
        $format = self::parse_name($first->get_filename())[2];

        $tmp = make_request_directory() . '/segment.' . $format;
        $out = fopen($tmp, 'wb');
        foreach (array_merge($merged ? [$merged] : [], array_values($parts)) as $file) {
            $in = $file->get_content_file_handle();
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        $fs = get_file_storage();
        if ($merged) {
            $merged->delete();
        }
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'quizaccess_examproctor',
            'filearea' => self::FILEAREA,
            'itemid' => $attemptid,
            'filepath' => '/',
            'filename' => $segment . '.' . $format,
            'userid' => $first->get_userid(),
            'mimetype' => self::FORMATS[$format],
        ], $tmp);
        foreach ($parts as $file) {
            $file->delete();
        }
    }

    /**
     * Merges every segment of an attempt that still has loose chunks (attempt finished, or page left mid-segment).
     *
     * @param \context_module $context
     * @param int $attemptid
     * @param int $olderthan Only segments whose newest chunk is older than this timestamp (0 = all).
     */
    public static function merge_all(\context_module $context, int $attemptid, int $olderthan = 0): void {
        $pending = [];
        foreach (self::area_files($context, $attemptid) as $file) {
            [$seg, $seq] = self::parse_name($file->get_filename());
            if ($seg !== null && $seq !== null) {
                $pending[$seg] = max($pending[$seg] ?? 0, $file->get_timecreated());
            }
        }
        foreach ($pending as $seg => $newest) {
            if (!$olderthan || $newest < $olderthan) {
                self::merge_segment($context, $attemptid, $seg);
            }
        }
    }

    /**
     * Playable segments of an attempt, oldest first.
     *
     * @param \context_module $context
     * @param int $attemptid
     * @return array[] Each: start (unix time), url, bytes, mimetype.
     */
    public static function segments(\context_module $context, int $attemptid): array {
        $segments = [];
        foreach (self::area_files($context, $attemptid) as $file) {
            [$seg, $seq] = self::parse_name($file->get_filename());
            if ($seg === null || $seq !== null) {
                continue;
            }
            $segments[$seg] = [
                'start' => intdiv($seg, 1000),
                'url' => \moodle_url::make_pluginfile_url($context->id, 'quizaccess_examproctor', self::FILEAREA,
                    $attemptid, '/', $file->get_filename())->out(false),
                'bytes' => $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
            ];
        }
        ksort($segments);
        return array_values($segments);
    }

    /**
     * Latest upload time and total size per attempt, for the monitor.
     *
     * @param \context_module $context
     * @return array attemptid => (object) [lastupload, bytes]
     */
    public static function stats(\context_module $context): array {
        global $DB;
        $sql = "SELECT itemid, MAX(timecreated) AS lastupload, SUM(filesize) AS bytes
                  FROM {files}
                 WHERE contextid = :contextid AND component = 'quizaccess_examproctor' AND filearea = :filearea
                   AND filename <> '.'
              GROUP BY itemid";
        return $DB->get_records_sql($sql, ['contextid' => $context->id, 'filearea' => self::FILEAREA]);
    }

    /**
     * Deletes all videos of a quiz.
     *
     * @param \context_module $context
     */
    public static function delete_for_context(\context_module $context): void {
        get_file_storage()->delete_area_files($context->id, 'quizaccess_examproctor', self::FILEAREA);
    }

    /**
     * Deletes the videos of one attempt.
     *
     * @param \context_module $context
     * @param int $attemptid
     */
    public static function delete_for_attempt(\context_module $context, int $attemptid): void {
        get_file_storage()->delete_area_files($context->id, 'quizaccess_examproctor', self::FILEAREA, $attemptid);
    }

    /**
     * Files of one attempt, without directories.
     *
     * @param \context_module $context
     * @param int $attemptid
     * @return \stored_file[]
     */
    protected static function area_files(\context_module $context, int $attemptid): array {
        return get_file_storage()->get_area_files($context->id, 'quizaccess_examproctor', self::FILEAREA, $attemptid,
            'filename', false);
    }

    /**
     * Splits "<segment>-<seq>.<ext>" or "<segment>.<ext>".
     *
     * @param string $filename
     * @return array [segment|null, seq|null, format|null]
     */
    protected static function parse_name(string $filename): array {
        if (!preg_match('/^(\d+)(?:-(\d+))?\.(webm|mp4)$/', $filename, $m)) {
            return [null, null, null];
        }
        return [(int) $m[1], ($m[2] ?? '') === '' ? null : (int) $m[2], $m[3]];
    }
}
