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
 * Live exam monitor: every attempt in progress with its violations, last activity, latest webcam photo and video
 * status, plus class-wide counts and the latest violations. Template context for quizaccess_examproctor/monitor.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monitor {

    /** Seconds without any sign of life before a student is shown as idle, then offline. */
    const IDLE_AFTER = 90;
    const OFFLINE_AFTER = 300;

    /** @var \stdClass */
    protected $quiz;
    /** @var \cm_info|\stdClass */
    protected $cm;
    /** @var \context_module */
    protected $context;
    /** @var int */
    protected $now;

    /**
     * @param \stdClass $quiz
     * @param \stdClass $cm
     * @param int|null $now
     */
    public function __construct(\stdClass $quiz, $cm, ?int $now = null) {
        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->context = \context_module::instance($cm->id);
        $this->now = $now ?? time();
    }

    /**
     * Template context.
     *
     * @return array
     */
    public function export(): array {
        global $DB;

        $settings = $DB->get_record('quizaccess_examproctor', ['quizid' => $this->quiz->id]);
        $max = $settings ? (int) $settings->maxviolations : 0;
        $recordvideo = $settings && !empty($settings->recordvideo);

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $attempts = $DB->get_records_sql(
            "SELECT qa.id, qa.userid, qa.attempt, qa.state, qa.preview, qa.timestart, qa.timefinish, qa.timemodified,
                    qa.timecheckstate, qa.currentpage, u.idnumber, $userfields
               FROM {quiz_attempts} qa
               JOIN {user} u ON u.id = qa.userid
              WHERE qa.quiz = :quizid
           ORDER BY qa.timestart DESC", ['quizid' => $this->quiz->id]);

        $inprogress = array_filter($attempts, fn($a) => in_array($a->state, ['inprogress', 'overdue']));
        $finished = array_filter($attempts, fn($a) => in_array($a->state, ['finished', 'abandoned']));

        $logs = $this->log_summary();
        $lastviolation = $this->latest_events(true);
        $videoevents = $this->latest_events(false, ['videostart', 'videofailed', 'videostopped']);
        $videostats = $recordvideo ? video::stats($this->context) : [];
        $photos = $this->latest_photos();

        $rows = [];
        $flagged = $offline = 0;
        foreach ($inprogress as $a) {
            $log = $logs[$a->id] ?? null;
            $violations = $log ? (int) $log->violations : 0;
            $video = $videostats[$a->id] ?? null;
            $photo = $photos[$a->id] ?? null;
            $lastactivity = max((int) $a->timemodified, (int) $a->timestart, $log ? (int) $log->lastevent : 0,
                $video ? (int) $video->lastupload : 0, $photo ? (int) $photo->timemodified : 0);
            $idle = $this->now - $lastactivity;
            $presence = $idle < self::IDLE_AFTER ? 'live' : ($idle < self::OFFLINE_AFTER ? 'idle' : 'offline');
            $flagged += $violations > 0 ? 1 : 0;
            $offline += $presence === 'offline' ? 1 : 0;

            $lv = $lastviolation[$a->id] ?? null;
            $rows[] = [
                'attemptid' => $a->id,
                'fullname' => fullname($a),
                'idnumber' => s($a->idnumber),
                'preview' => (bool) $a->preview,
                'overdue' => $a->state === 'overdue',
                'photourl' => $photo ? $photo->webcampicture : null,
                'started' => userdate($a->timestart, get_string('strftimetime', 'core_langconfig')),
                'timeleft' => $a->timecheckstate ? $this->duration(max(0, (int) $a->timecheckstate - $this->now)) : null,
                'timeleftlow' => $a->timecheckstate && $a->timecheckstate - $this->now < 5 * MINSECS,
                'page' => (int) $a->currentpage + 1,
                'violations' => $violations,
                'maxviolations' => $max,
                'violationlevel' => $violations === 0 ? 'none' : ($max && $violations >= $max - 1 ? 'high' : 'some'),
                'lastviolation' => $lv ? $this->event_name($lv->eventtype) : null,
                'lastviolationago' => $lv ? $this->ago((int) $lv->timecreated) : null,
                'presence' => $presence,
                'presencetext' => get_string('presence_' . $presence, 'quizaccess_examproctor'),
                'lastactivityago' => $this->ago($lastactivity),
                'video' => $recordvideo ? $this->video_state($videoevents[$a->id] ?? null, $video) : null,
                'eventsurl' => (new \moodle_url('/mod/quiz/accessrule/examproctor/report.php',
                    ['cmid' => $this->cm->id, 'attempt' => $a->id]))->out(false),
                'videourl' => $recordvideo ? (new \moodle_url('/mod/quiz/accessrule/examproctor/video.php',
                    ['cmid' => $this->cm->id, 'attempt' => $a->id]))->out(false) : null,
                'reviewurl' => (new \moodle_url('/mod/quiz/review.php', ['attempt' => $a->id]))->out(false),
            ];
        }
        // Most urgent first: violations, then offline, then name.
        usort($rows, fn($x, $y) => [$y['violations'], $y['presence'] === 'offline', $x['fullname']]
            <=> [$x['violations'], $x['presence'] === 'offline', $y['fullname']]);

        return [
            'cmid' => $this->cm->id,
            'quizname' => format_string($this->quiz->name),
            'updated' => userdate($this->now, '%H:%M:%S'),
            'recordvideo' => $recordvideo,
            'tiles' => [
                ['label' => get_string('mon_inprogress', 'quizaccess_examproctor'), 'value' => count($inprogress),
                    'variant' => 'primary'],
                ['label' => get_string('mon_flagged', 'quizaccess_examproctor'), 'value' => $flagged,
                    'variant' => $flagged ? 'danger' : 'secondary'],
                ['label' => get_string('mon_offline', 'quizaccess_examproctor'), 'value' => $offline,
                    'variant' => $offline ? 'warning' : 'secondary'],
                ['label' => get_string('mon_submitted', 'quizaccess_examproctor'), 'value' => count($finished),
                    'variant' => 'success'],
                ['label' => get_string('mon_notstarted', 'quizaccess_examproctor'), 'value' => $this->count_not_started(),
                    'variant' => 'secondary'],
            ],
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'feed' => $this->feed(),
            'reporturl' => (new \moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $this->cm->id]))
                ->out(false),
            'resultsurl' => (new \moodle_url('/mod/quiz/report.php', ['id' => $this->cm->id, 'mode' => 'overview']))
                ->out(false),
        ];
    }

    /**
     * Violations and last event time per attempt.
     *
     * @return array attemptid => record
     */
    protected function log_summary(): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT attemptid, SUM(isviolation) AS violations, MAX(timecreated) AS lastevent
               FROM {quizaccess_examproctor_log}
              WHERE quizid = :quizid
           GROUP BY attemptid", ['quizid' => $this->quiz->id]);
    }

    /**
     * Latest event per attempt: the latest violation, or the latest of the given types.
     *
     * @param bool $violations
     * @param string[] $types
     * @return array attemptid => record
     */
    protected function latest_events(bool $violations, array $types = []): array {
        global $DB;
        $params = ['quizid' => $this->quiz->id];
        if ($violations) {
            $where = 'isviolation = 1';
        } else {
            [$insql, $inparams] = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED);
            $where = "eventtype $insql";
            $params += $inparams;
        }
        return $DB->get_records_sql(
            "SELECT l.attemptid, l.eventtype, l.details, l.timecreated
               FROM {quizaccess_examproctor_log} l
               JOIN (SELECT attemptid, MAX(id) AS maxid
                       FROM {quizaccess_examproctor_log}
                      WHERE quizid = :quizid AND $where
                   GROUP BY attemptid) x ON x.maxid = l.id", $params);
    }

    /**
     * Latest webcam photo per attempt from the webcam photo plugin (quizaccess_proctoring), if installed.
     *
     * @return array attemptid => record (webcampicture URL, timemodified)
     */
    protected function latest_photos(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('quizaccess_proctoring_logs')) {
            return [];
        }
        // That plugin stores the course module id in "quizid" and the attempt id in "status".
        return $DB->get_records_sql(
            "SELECT l.status AS attemptid, l.webcampicture, l.timemodified
               FROM {quizaccess_proctoring_logs} l
               JOIN (SELECT status, MAX(id) AS maxid
                       FROM {quizaccess_proctoring_logs}
                      WHERE quizid = :cmid AND webcampicture <> ''
                   GROUP BY status) x ON x.maxid = l.id", ['cmid' => $this->cm->id]);
    }

    /**
     * The 15 latest violations in the quiz.
     *
     * @return array
     */
    protected function feed(): array {
        global $DB;
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $events = $DB->get_records_sql(
            "SELECT l.id, l.attemptid, l.eventtype, l.details, l.timecreated, $userfields
               FROM {quizaccess_examproctor_log} l
               JOIN {user} u ON u.id = l.userid
              WHERE l.quizid = :quizid AND l.isviolation = 1
           ORDER BY l.timecreated DESC, l.id DESC", ['quizid' => $this->quiz->id], 0, 15);
        return array_values(array_map(fn($e) => [
            'fullname' => fullname($e),
            'event' => $this->event_name($e->eventtype),
            'details' => s($e->details),
            'time' => userdate($e->timecreated, '%H:%M:%S'),
            'ago' => $this->ago((int) $e->timecreated),
        ], $events));
    }

    /**
     * Students who can attempt the quiz and have not started.
     *
     * @return int
     */
    protected function count_not_started(): int {
        global $DB;
        [$esql, $params] = get_enrolled_sql($this->context, 'mod/quiz:attempt', 0, true);
        $params['quizid'] = $this->quiz->id;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM ($esql) eu
              WHERE NOT EXISTS (SELECT 1 FROM {quiz_attempts} qa WHERE qa.quiz = :quizid AND qa.userid = eu.id)",
            $params);
    }

    /**
     * Video status of an attempt: recording, camera problem, or not started.
     *
     * @param \stdClass|null $event Latest video event.
     * @param \stdClass|null $stats Upload stats.
     * @return array
     */
    protected function video_state(?\stdClass $event, ?\stdClass $stats): array {
        $recent = $stats && $this->now - (int) $stats->lastupload < 3 * video::CHUNK_SECONDS;
        if ($event && $event->eventtype !== 'videostart') {
            $state = 'problem';
        } else if ($recent) {
            $state = 'recording';
        } else {
            $state = $stats ? 'stalled' : 'none';
        }
        return [
            'state' => $state,
            'text' => get_string('video_' . $state, 'quizaccess_examproctor'),
            'detail' => $event && $state === 'problem' ? s($event->details) : '',
            'size' => $stats ? display_size((int) $stats->bytes) : '',
        ];
    }

    /**
     * Event type label.
     *
     * @param string $type
     * @return string
     */
    protected function event_name(string $type): string {
        $key = 'event_' . $type;
        return get_string_manager()->string_exists($key, 'quizaccess_examproctor')
            ? get_string($key, 'quizaccess_examproctor') : s($type);
    }

    /**
     * "2 min ago".
     *
     * @param int $time
     * @return string
     */
    protected function ago(int $time): string {
        return get_string('ago', 'core_message', $this->duration(max(0, $this->now - $time)));
    }

    /**
     * Short duration: "45 secs", "12 mins", "1 hour 5 mins".
     *
     * @param int $seconds
     * @return string
     */
    protected function duration(int $seconds): string {
        return $seconds < MINSECS ? get_string('numseconds', 'core', $seconds)
            : format_time($seconds - $seconds % MINSECS);
    }
}
