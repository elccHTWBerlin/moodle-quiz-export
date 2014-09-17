moodle-quiz-export
==================

This is a quiz report plugin for [moodle](https://moodle.org/) to export quiz attempts as pdf. Single quiz attempt exports are possible as well as bulk exports as zip files.

# Moodle Versions
Due to some bigger changes since moodle verion 2.6 it's required to use different plugin versions depending on the moodle version you use.

## Moodle < 2.5
Use version 1.x of this plugin.

## Moodle >= 2.6
Use version 2.x of this plugin.

# Dependencies
* wkhtmltopdf (http://wkhtmltopdf.org/) must installed on the system and configured in config.php

# Installation:
* switch to /path/to/moodle/mod/quiz/report/
* execute git clone https://github.com/elcc/moodle-quiz-export.git export
* depending on your moodle version checkout either branch quiz-export_v1 or quiz-export_v2: git checkout quiz-export_v1
* adjust settings in export/config.php
* on moodle page go to "Site Administration" -> "Notifications" and follow the instructions

# Technical Information:
* this plugin saves html, pdf and zip files temporary in the folder returned by [sys_get_temp_dir()](http://www.php.net/manual/en/function.sys-get-temp-dir.php) PHP function.
	* this files are created with [tempnam()](http://www.php.net/manual/en/function.tempnam.php) function
	* and modified with [rename()](http://mx2.php.net/manual/en/function.rename.php) and [chmod()](http://mx2.php.net/manual/en/function.chmod.php)
* zip archives are generated with the [ZipArchive](http://mx2.php.net/manual/en/class.ziparchive.php) class
