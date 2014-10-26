<?php

/**
 * This file defines the export engine class.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/config.php');
require_once(dirname(__FILE__) . '/config.php');


/**
 * Quiz export engine class.
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_engine {

	/**
	 * Actual question page assignment like in quiz settings.
	 */
	const PAGEMODE_TRUEPAGE = 0;

	/**
	 * One question per page.
	 */
	const PAGEMODE_QUESTIONPERPAGE = 1;

	/**
	 * all questions on single page.
	 */
	const PAGEMODE_SINGLEPAGE = 2;

	/**
	 * Exports the given quiz attempt to a pdf file.
	 * @param  quiz_attempt $attemptobj The quiz attempt to export.
	 * @param  int $pagemode   The page break mode used to render the quiz review.
	 *                         One of PAGEMODE_TRUEPAGE, PAGEMODE_QUESTIONPERPAGE or PAGEMODE_SINGLEPAGE
	 * @return file_path       File path and name as string of the pdf file.
	 */
	public function a2pdf($attemptobj, $pagemode) {
		switch ($pagemode) {
			default:
			case quiz_export_engine::PAGEMODE_TRUEPAGE:
				$html_files = $this->questions_paged($attemptobj);
				break;
			case quiz_export_engine::PAGEMODE_QUESTIONPERPAGE:
				$html_files = $this->question_per_page($attemptobj);
				break;
			case quiz_export_engine::PAGEMODE_SINGLEPAGE:
				$html_files = $this->all_questions($attemptobj);
				break;
		}

		$tmp_dir = sys_get_temp_dir();
		$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
		$tmp_err_file = $tmp_file .".txt";
		rename($tmp_file, $tmp_err_file);
		chmod($tmp_err_file, 0644);

		$options = '';
		$options = $options.' --cookie MoodleSession '. $_COOKIE['MoodleSession'];
		session_write_close();
		$tmp_pdf_files = array();
		foreach ($html_files as $html_file) {
			$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
			$tmp_pdf_file = $tmp_file .".pdf";
			rename($tmp_file, $tmp_pdf_file);
			chmod($tmp_pdf_file, 0644);
			$cmd = quiz_export_config::WKHTMLTOPDF .$options .' '. $html_file .' '. $tmp_pdf_file .' 2> '. $tmp_err_file;
			$shell_exec_stdout = shell_exec($cmd);
			$tmp_pdf_files[] = $tmp_pdf_file;
			unlink($html_file);
		}

		if (sizeof($tmp_pdf_files)!=1) {
			$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
			$pdf_output = $tmp_file .".pdf";
			rename($tmp_file, $pdf_output);
			chmod($pdf_output, 0644);
			$input_files = implode(' ', $tmp_pdf_files);
			$options = ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile='.$pdf_output;
			$cmd = quiz_export_config::GS .$options .' ' .$input_files .' 2> '. $tmp_err_file;
			$shell_exec_stdout = shell_exec($cmd);
			echo $cmd;

			foreach ($tmp_pdf_files as $tmp_pdf_file) {
				unlink($tmp_pdf_file);
			}
		} else {
			$pdf_output = $tmp_pdf_files[0];
		}

		// readfile($tmp_err_file);
		unlink($tmp_err_file);
		
		return $pdf_output;
	}
	
	protected function question_per_page($attemptobj) {
		$tmp_html_files = array();
		$showall = false;
		$num_pages = $attemptobj->get_num_pages();

		for ($page=0; $page < $num_pages; $page++) {
			$questionids = $attemptobj->get_slots($page);
			$lastpage = $attemptobj->is_last_page($page);

			foreach ($questionids as $questionid) {
				// we have just one question id but an array is required from render function
				$slots = array();
				$slots[] = $questionid;
				
				$tmp_dir = sys_get_temp_dir();
				$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
				$tmp_html_file = $tmp_file .".html";
				rename($tmp_file, $tmp_html_file);
				chmod($tmp_html_file, 0644);

				$output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);
				file_put_contents($tmp_html_file, $output);

				$tmp_html_files[] = $tmp_html_file;
			}
		}

		return $tmp_html_files;
	}

	protected function questions_paged($attemptobj) {
		$tmp_html_files = array();
		$showall = false;
		$num_pages = $attemptobj->get_num_pages();

		for ($page=0; $page < $num_pages; $page++) {
			$slots = $attemptobj->get_slots($page);
			$lastpage = $attemptobj->is_last_page($page);
			
			$tmp_dir = sys_get_temp_dir();
			$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
			$tmp_html_file = $tmp_file .".html";
			rename($tmp_file, $tmp_html_file);
			chmod($tmp_html_file, 0644);

			$output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);
			file_put_contents($tmp_html_file, $output);

			$tmp_html_files[] = $tmp_html_file;
		}

		return $tmp_html_files;
	}

	protected function all_questions($attemptobj) {
		$slots = $attemptobj->get_slots();
		$showall = true;
		$lastpage = true;
		$page = 0;

		$tmp_dir = sys_get_temp_dir();
		$tmp_file = tempnam($tmp_dir, "mdl-qexp_");
		$tmp_html_file = $tmp_file .".html";
		rename($tmp_file, $tmp_html_file);
		chmod($tmp_html_file, 0644);

		$output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);
		file_put_contents($tmp_html_file, $output);

		return array($tmp_html_file);
	}

	protected function get_review_html($attemptobj, $slots, $page, $showall, $lastpage) {
		$html = $this->render($attemptobj, $slots, $page, $showall, $lastpage);
		return $html;
	}

	protected function render($attemptobj, $slots, $page, $showall, $lastpage) {
		global $PAGE, $CFG;

		$options = $attemptobj->get_display_options(true);

		// ugly hack to get a new page
		$this->setup_new_page();

		$url = new moodle_url('/mod/quiz/report/export/a2pdf.php', array('attempt'=>$attemptobj->get_attemptid()));
		$PAGE->set_url($url);

		// Set up the page header.
		// $headtags = $attemptobj->get_html_head_contributions($page, $showall);
		// $PAGE->set_title($attemptobj->get_quiz_name());
		// $PAGE->set_heading($attemptobj->get_course()->fullname);

		$summarydata = $this->summary_table($attemptobj, $options);

		// display only content
		// $PAGE->force_theme('standard');
		$PAGE->set_pagelayout('embedded');

		$output = $PAGE->get_renderer('mod_quiz');

		// fool out mod_quiz renderer:
		// 		set $page = 0 for showing comple summary table on every page
		// 			side effect: breaks next page links
		// echo $output->review_summary_table($summarydata, $page);
		return $output->review_page($attemptobj, $slots, $page, $showall, $lastpage, $options, $summarydata);
	}

	/**
	 * Generates a quiz review summary table.
	 * The Code is original from mod/quiz/review.php and just wrapped to a function.
	 * @param quiz_attempt $attemptobj The attempt object the summary is for.
	 * @param mod_quiz_display_options $options Extra options for the attempt.
	 * @return array contains all table data for summary table
	 */
	protected function summary_table($attemptobj, $options) {
		global $USER, $DB;

		// Work out some time-related things.
		$attempt = $attemptobj->get_attempt();
		$quiz = $attemptobj->get_quiz();
		$overtime = 0;

		if ($attempt->state == quiz_attempt::FINISHED) {
		    if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
		        if ($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
		            $overtime = $timetaken - $quiz->timelimit;
		            $overtime = format_time($overtime);
		        }
		        $timetaken = format_time($timetaken);
		    } else {
		        $timetaken = "-";
		    }
		} else {
		    $timetaken = get_string('unfinished', 'quiz');
		}

		// Prepare summary informat about the whole attempt.
		$summarydata = array();
		if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {
		    // If showuserpicture is true, the picture is shown elsewhere, so don't repeat it.
		    $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
		    $usrepicture = new user_picture($student);
		    $usrepicture->courseid = $attemptobj->get_courseid();
		    $summarydata['user'] = array(
		        'title'   => $usrepicture,
		        'content' => new action_link(new moodle_url('/user/view.php', array(
		                                'id' => $student->id, 'course' => $attemptobj->get_courseid())),
		                          fullname($student, true)),
		    );
		}

		// Timing information.
		$summarydata['startedon'] = array(
		    'title'   => get_string('startedon', 'quiz'),
		    'content' => userdate($attempt->timestart),
		);

		$summarydata['state'] = array(
		    'title'   => get_string('attemptstate', 'quiz'),
		    'content' => quiz_attempt::state_name($attempt->state),
		);

		if ($attempt->state == quiz_attempt::FINISHED) {
		    $summarydata['completedon'] = array(
		        'title'   => get_string('completedon', 'quiz'),
		        'content' => userdate($attempt->timefinish),
		    );
		    $summarydata['timetaken'] = array(
		        'title'   => get_string('timetaken', 'quiz'),
		        'content' => $timetaken,
		    );
		}

		if (!empty($overtime)) {
		    $summarydata['overdue'] = array(
		        'title'   => get_string('overdue', 'quiz'),
		        'content' => $overtime,
		    );
		}

		// Show marks (if the user is allowed to see marks at the moment).
		$grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
		if ($options->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz)) {

		    if ($attempt->state != quiz_attempt::FINISHED) {
		        // Cannot display grade.

		    } else if (is_null($grade)) {
		        $summarydata['grade'] = array(
		            'title'   => get_string('grade', 'quiz'),
		            'content' => quiz_format_grade($quiz, $grade),
		        );

		    } else {
		        // Show raw marks only if they are different from the grade (like on the view page).
		        if ($quiz->grade != $quiz->sumgrades) {
		            $a = new stdClass();
		            $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
		            $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
		            $summarydata['marks'] = array(
		                'title'   => get_string('marks', 'quiz'),
		                'content' => get_string('outofshort', 'quiz', $a),
		            );
		        }

		        // Now the scaled grade.
		        $a = new stdClass();
		        $a->grade = html_writer::tag('b', quiz_format_grade($quiz, $grade));
		        $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
		        if ($quiz->grade != 100) {
		            $a->percent = html_writer::tag('b', format_float(
		                    $attempt->sumgrades * 100 / $quiz->sumgrades, 0));
		            $formattedgrade = get_string('outofpercent', 'quiz', $a);
		        } else {
		            $formattedgrade = get_string('outof', 'quiz', $a);
		        }
		        $summarydata['grade'] = array(
		            'title'   => get_string('grade', 'quiz'),
		            'content' => $formattedgrade,
		        );
		    }
		}

		// Feedback if there is any, and the user is allowed to see it now.
		$feedback = $attemptobj->get_overall_feedback($grade);
		if ($options->overallfeedback && $feedback) {
		    $summarydata['feedback'] = array(
		        'title'   => get_string('feedback', 'quiz'),
		        'content' => $feedback,
		    );
		}

		return $summarydata;
	}

	/**
	 * Overwrites the $PAGE global with a new moodle_page instance.
	 * Code is original from lib/setup.php and lib/adminlib.php
	 * @return void 
	 */
	protected function setup_new_page() {
		global $CFG, $PAGE;

		if (!empty($CFG->moodlepageclass)) {
		    if (!empty($CFG->moodlepageclassfile)) {
		        require_once($CFG->moodlepageclassfile);
		    }
		    $classname = $CFG->moodlepageclass;
		} else {
		    $classname = 'moodle_page';
		}
		$PAGE = new $classname();
		unset($classname);

		$PAGE->set_context(null);
	}
}
