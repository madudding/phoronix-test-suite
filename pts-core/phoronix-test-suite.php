<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2011, Phoronix Media
	Copyright (C) 2008 - 2011, Michael Larabel
	phoronix-test-suite.php: The main code for initalizing the Phoronix Test Suite

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

if(defined('LC_NUMERIC'))
{
	setlocale(LC_NUMERIC, 'C');
}
define('PTS_PATH', dirname(dirname(__FILE__)) . '/');

// PTS_MODE types
// CLIENT = Standard Phoronix Test Suite Client
// LIB = Only load select PTS files
// SILENT = Load all normal pts-core files, but don't run client code
define('PTS_MODE', in_array(($m = getenv('PTS_MODE')), array('CLIENT', 'LIB', 'SILENT')) ? $m : 'CLIENT');

// Any PHP default memory limit should be fine for PTS, until you run image quality comparison tests that begins to consume memory
ini_set('memory_limit', '256M');

if(PTS_MODE == 'CLIENT' && ini_get('open_basedir') != false)
{
	if(pts_client::open_basedir_check() == false)
	{
		echo PHP_EOL . 'ERROR: Your php.ini configuration open_basedir setting is preventing ' . PTS_PATH . ' from loading.' . PHP_EOL;
		return false;
	}
}

require(PTS_PATH . 'pts-core/pts-core.php');

if(PTS_MODE != 'CLIENT')
{
	// pts-core is acting as a library, return now since no need to run client code
	return;
}

if(ini_get('date.timezone') == null)
{
	date_default_timezone_set('UTC');
}

// Needed for shutdown functions
// declare(ticks = 1);

$sent_command = strtolower(str_replace('-', '_', (isset($argv[1]) ? $argv[1] : null)));
$quick_start_options = array('dump_possible_options');
define('QUICK_START', in_array($sent_command, $quick_start_options));

pts_client::program_requirement_checks(true);
pts_client::init(); // Initalize the Phoronix Test Suite (pts-core) client
$pass_args = array();

if(is_file(PTS_PATH . 'pts-core/commands/' . $sent_command . '.php') == false)
{
	$replaced = false;

	if(pts_module::valid_run_command($sent_command))
	{
		$replaced = true;
	}
	else if(isset($argv[1]) && strpos($argv[1], '.openbenchmarking') !== false && is_readable($argv[1]))
	{
		$sent_command = 'openbenchmarking_launcher';
		$argv[2] = $argv[1];
		$argc = 3;
		$replaced = true;
	}
	else
	{
		$alias_file = pts_file_io::file_get_contents(PTS_CORE_STATIC_PATH . 'lists/option-command-aliases.list');

		foreach(pts_strings::trim_explode("\n", $alias_file) as $alias_line)
		{
			list($link_cmd, $real_cmd) = pts_strings::trim_explode('=', $alias_line);

			if($link_cmd == $sent_command)
			{
				$sent_command = $real_cmd;
				$replaced = true;
				break;
			}
		}
	}

	if($replaced == false)
	{
		// Show help command, since there are no valid commands
		$sent_command = 'help';
	}
}

define('PTS_USER_LOCK', PTS_USER_PATH . 'run_lock');

if(!QUICK_START)
{
	if(pts_client::create_lock(PTS_USER_LOCK) == false)
	{
		pts_client::$display->generic_warning('It appears that the Phoronix Test Suite is already running.' . PHP_EOL . 'For proper results, only run one instance at a time.');
	}

	register_shutdown_function(array('pts_client', 'process_shutdown_tasks'));

	pts_network::client_startup();

	if(pts_client::read_env('PTS_IGNORE_MODULES') == false)
	{
		pts_client::module_framework_init(); // Initialize the PTS module system
	}
}

// Read passed arguments
for($i = 2; $i < $argc && isset($argv[$i]); $i++)
{
	array_push($pass_args, $argv[$i]);
}

if(QUICK_START == false)
{
	pts_client::user_agreement_check($sent_command);
	pts_client::user_hardware_software_reporting();

	// OpenBenchmarking.org
	pts_openbenchmarking::refresh_repository_lists();
}

pts_client::execute_command($sent_command, $pass_args); // Run command

if(QUICK_START == false)
{
	pts_client::release_lock(PTS_USER_LOCK);
}

?>
