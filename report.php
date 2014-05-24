<?php

/**
 * This file defines the quiz export report class.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/export/export_table.php');

/**
 * Quiz report subclass for the export report.
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// class quiz_export_report extends quiz_default_report {
class quiz_export_report extends quiz_attempts_report {

    public function display($quiz, $cm, $course) {
        global $PAGE;
        
        // this inits the quiz_attempts_report (parent class) functionality
        list($currentgroup, $students, $groupstudents, $allowed) =
            $this->init('export', 'quiz_export_settings_form', $quiz, $cm, $course);

        // this creates a new options object and ...
        $options = new quiz_export_options('export', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);
        } else {
            $options->process_settings_from_params();
        }
        // write the information from options back to form (in case options changed due to params)
        $this->form->set_data($options->get_initial_form_data());

        // 
        $questions = quiz_report_get_significant_questions($quiz);

        // 
        $table = new quiz_export_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());

        // downloading?
        // $table->is_downloading('csv', 'filename', 'Sheettitle');

        // set layout e.g. for hiding navigation
        // nothing but content
        // $PAGE->set_pagelayout('embedded');
        // just breadcrump bar and title
        // $PAGE->set_pagelayout('print');

        // Start output.

        // print moodle headers (header, navigation, etc.) only if not downloading
        if(!$table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);
        }

        // // no idea what this operated
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector
            groups_print_activity_menu($cm, $options->get_url());
        }

        $hasquestions = quiz_questions_in_quiz($quiz->questions);
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$students) {
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        } else if ($currentgroup && !$groupstudents) {
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
        }

        $this->form->display();

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            list($fields, $from, $where, $params) = $table->base_sql($allowed);
            // function documentation says we don't need to do this
            // $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            // display a checkbox column for bulk export
            $columns[] = 'checkbox';
            $headers[] = null;

            $this->add_user_columns($table, $columns, $headers);

            // $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            // Set up the table.
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            // $table->set_attribute('class', 'generaltable generalbox grades');
            // Print the table
            $table->out($options->pagesize, true);
        }
    }

    /**
     * Process any submitted actions.
     * @param object $quiz the quiz settings.
     * @param object $cm the cm object for the quiz.
     * @param int $currentgroup the currently selected group.
     * @param array $groupstudents the students in the current group.
     * @param array $allowed the users whose attempt this user is allowed to modify.
     * @param moodle_url $redirecturl where to redircet to after a successful action.
     */
    protected function process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl) {
        // parent::process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl);

        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('export', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    // require_capability('mod/quiz:deleteattempts', $this->context);
                    $this->export_attempts($quiz, $cm, $attemptids, $allowed);
                    redirect($redirecturl);
                }
            }
        }
    }

    /**
     * Export the quiz attempts
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param array $attemptids the list of attempt ids to export.
     * @param array $allowed This list of userids that are visible in the report.
     *      Users can only export attempts that they are allowed to see in the report.
     *      Empty means all users.
     */
    protected function export_attempts($quiz, $cm, $attemptids, $allowed) {
        // 
    }
}
