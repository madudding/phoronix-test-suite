<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2009 - 2010, Phoronix Media
	Copyright (C) 2009 - 2010, Michael Larabel
	gui_gtk_events.php: A module used in conjunction with the Phoronix Test Suite GTK2 GUI interface

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class gui_gtk_events extends pts_module_interface
{
	const module_name = "GUI GTK Events";
	const module_version = "2.0.0";
	const module_description = "This module is used in conjunction with the Phoronix Test Suite GTK2 GUI interface. This module is automatically loaded when needed.";
	const module_author = "Phoronix Media";

	static $progress_window;

	static $test_install_count = 0;
	static $test_install_current = null;
	static $test_install_pos = 0;
	static $install_overall_percent = 0;
	static $test_download_count = 0;
	static $test_download_current = null;
	static $test_download_pos = 0;

	static $test_run_count = 0;
	static $test_run_pos = 0;
	static $tests_remaining_to_run;

	public static function __startup()
	{
		if(!class_exists("gui_gtk"))
		{
			// The GTK interface isn't being used
			return pts_module::MODULE_UNLOAD;
		}
	}

	//
	// Installation Functions
	//

	public static function __pre_install_process(&$test_install_manager)
	{
		self::$test_install_pos = 0;
		self::$test_install_count = $test_install_manager->tests_to_install_count();
		self::$progress_window = new pts_gtk_advanced_progress_window("Phoronix Test Suite: Test Installation");
		self::$progress_window->update_progress_bar(0, " ", 0, "Preparing Install Process");
	}
	public static function __pre_test_download($obj)
	{
		$test_profile = new pts_test_profile($obj[0]);
		self::$test_install_current = $test_profile->get_name();
		self::$test_download_count = count($obj[1]);
		self::$progress_window->update_progress_bar(0, "Downloading: " . $obj[1][0]->get_filename() . " (" . round($obj[1][0]->get_filesize() / 1048576, 1) . "MB)", self::$install_overall_percent, "Installing: " . self::$test_install_current);
	}
	public static function __interim_test_download($obj)
	{
		self::$test_download_pos++;

		if(isset($obj[1][self::$test_download_pos]))
		{
			self::$progress_window->update_progress_bar((self::$test_download_pos / self::$test_download_count) * 50, "Downloading: " . $obj[1][self::$test_download_pos]->get_filename() . " (" . round($obj[1][self::$test_download_pos]->get_filesize() / 1048576, 1) . "MB)", self::$install_overall_percent, "Installing: " . self::$test_install_current);
		}
	}
	public static function __pre_test_install($identifier)
	{
		self::$test_install_current = $identifier;
		self::$progress_window->update_progress_bar(50, "Running Installation Script", self::$install_overall_percent, "Installing: " . self::$test_install_current);
	}
	public static function __post_test_install($obj = null)
	{
		self::$progress_window->update_progress_bar(100, "Installation Completed", self::$install_overall_percent, "Installing: " . self::$test_install_current);
		self::$test_install_pos++;
		self::$install_overall_percent = (self::$test_install_pos / self::$test_install_count) * 100;
	}
	public static function __post_install_process()
	{
		self::$progress_window->completed();
	}

	//
	// Run Functions
	//

	public static function __pre_run_process($test_run_manager)
	{
		self::$tests_remaining_to_run = array();

		foreach($test_run_manager->get_tests_to_run() as $test_run_request)
		{
			array_push(self::$tests_remaining_to_run, $test_run_request->get_identifier());
		}

		self::$test_run_pos = 0;
		self::$test_run_count = count(self::$tests_remaining_to_run);

		self::$progress_window = new pts_gtk_advanced_progress_window(pts_title());
		self::$progress_window->update_progress_bar(0, " ", 0, " ");
	}
	public static function __pre_test_run($pts_test_result)
	{
		array_shift(self::$tests_remaining_to_run);
		self::$progress_window->update_progress_bar(0, "<b>" . $pts_test_result->get_test_profile()->get_test_title() . "</b>" . ", Run " . ($pts_test_result->trial_run_count() + 1) . " of " . $pts_test_result->get_test_profile()->get_times_to_run(), (self::$test_run_pos / self::$test_run_count) * 100, self::test_run_position(1) . self::run_time_remaining($pts_test_result));
	}
	public static function __interim_test_run($pts_test_result)
	{
		self::$progress_window->update_progress_bar(($pts_test_result->trial_run_count() / $pts_test_result->get_test_profile()->get_times_to_run()) * 100, "<b>" . $pts_test_result->get_test_profile()->get_test_title() . "</b>" . ", Run " . ($pts_test_result->trial_run_count() + 1) . " of " . $pts_test_result->get_test_profile()->get_times_to_run(), ((self::$test_run_pos + ($pts_test_result->trial_run_count() / $pts_test_result->get_test_profile()->get_times_to_run())) / self::$test_run_count) * 100, self::test_run_position(1) . self::run_time_remaining($pts_test_result));
	}
	public static function __post_test_run($pts_test_result)
	{
		self::$test_run_pos++;
		self::run_time_remaining($pts_test_result);
		self::$progress_window->update_progress_bar(100, "<b>" . $pts_test_result->get_test_profile()->get_test_title() . ":</b>" . " Run " . $pts_test_result->trial_run_count() . " of " . $pts_test_result->get_test_profile()->get_times_to_run(), (self::$test_run_pos / self::$test_run_count) * 100, self::test_run_position(0) . self::run_time_remaining($pts_test_result));
	}
	public static function __post_run_process()
	{
		if(self::$progress_window != null)
		{
			self::$progress_window->completed();
			self::$progress_window = null;
		}
	}
	protected static function test_run_position($add_to_run = 0)
	{
		return "<b>Test " . (self::$test_run_pos + $add_to_run) . " of " . self::$test_run_count . ":</b> ";
	}
	protected static function run_time_remaining(&$test_result)
	{
		$test_run_position = pts_read_assignment("TEST_RUN_POSITION");
		$test_run_count = pts_read_assignment("TEST_RUN_COUNT");

		if(self::$test_run_count == $test_run_count)
		{
			$remaining_length = 0;
			//$remaining_length = pts_estimated_run_time(self::$tests_remaining_to_run);
			$estimated_length = $test_result->get_test_profile()->get_estimated_run_time();

			if($estimated_length > 1)
			{
				$remaining_length += $estimated_length * (($test_result->get_test_profile()->get_times_to_run() - $test_result->trial_run_count()) / $test_result->get_test_profile()->get_times_to_run());
			}

			if($remaining_length > 0)
			{
				return pts_strings::format_time($remaining_length, "SECONDS", true) . " Remaining";
				//self::$progress_window->update_secondary_label("Estimated Time Remaining: " . $time_remaining);
			}
		}
	}
}

?>
