<?php

/**
 * This file holds the configuration for the quiz_export plugin.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Configuration class
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_config {
	/**
	 * The wkhtmltopdf command with optional options.
	 * See wkhtmltopdf man page for detailed information. Some tips below:
	 * 
	 * --javascript-delay 1000 is recommended because some questiontypes uses javascript for rendering.
	 * And otherwise some js files may be not loaded or ready with calculations until wkhtmltopdf starts rendering.
	 * Increase this value if loading of scripts needs longer.
	 * 
	 * --user-style-sheet style.css allows to pass in additonal css styles
	 * html {width: 800px;} recommended to avoid scaling and positioning problems.
	 * (800 because wkhtmltopdf's screen resolution is hardcoded to 800x600)
	 * 
	 * e.g.:
	 * "/usr/local/bin/wkhtmltopdf --javascript-delay 1500 --user-style-sheet convert-style.css"
	 */
	const WKHTMLTOPDF = "wkhtmltopdf";

	/**
	 * Default pagemode for export:
	 * PAGEMODE_TRUEPAGE, PAGEMODE_QUESTIONPERPAGE or PAGEMODE_SINGLEPAGE.
	 * For more information about pagemode see export.php.
	 */
	const PAGEMODE_DEFAULT = quiz_export_engine::PAGEMODE_TRUEPAGE;

	/**
	 * The gs command with optional options.
	 * This is used to concat PDFs.
	 */
	const GS = "gs";
}
