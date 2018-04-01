<?php
/**
 * @package     Joomla.Site
 * @subpackage  RoboFile
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This is joomla project's console command file for Robo.li task runner.
 *
 * Do a `$ composer install` afterwards you will be able to execute robo like
 * `$ ./libraries/vendor/bin/robo` to see a list of commands
 *
 * @see         http://robo.li/
 */
require_once __DIR__ . '/libraries/vendor/autoload.php';

if (!defined('JPATH_BASE'))
{
	define('JPATH_BASE', __DIR__);
}

/**
 * System Test (Codeception) test execution for Joomla!
 *
 * @package  RoboFile
 *
 * @since    3.7.3
 */
class RoboFile extends \Robo\Tasks
{
	use JoomlaRobo\Tasks;

	/**
	 * Path to the Selenium folder#
	 *
	 * @var   string
	 * @since  3.7.3
	 */
	const SELENIUM_FOLDER = __DIR__ . '/libraries/vendor/joomla-projects/selenium-server-standalone';

	/**
	 * Path to the vendor folder
	 *
	 * @var   string
	 * @since  3.7.3
	 */
	private $vendorPath = 'libraries/vendor/';

	/**
	 * Path to the tests
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	private $testsPath = 'libraries/vendor/joomla/test-system/src/';

	/**
	 * Local configuration parameters
	 *
	 * @var    array
	 * @since  3.7.3
	 */
	private $configuration = array();

	/**
	 * @var array | null
	 * @since  3.7.3
	 */
	private $suiteConfig;

	/**
	 * Path to the local CMS test folder
	 *
	 * @var    string
	 * @since  3.7.3
	 */
	protected $cmsPath = null;

	/**
	 * RoboFile constructor.
	 *
	 * @since   3.7.3
	 */
	public function __construct()
	{
		$this->configuration = $this->getConfiguration();
		$this->cmsPath       = $this->getTestingPath();

		// Set default timezone (so no warnings are generated if it is not set)
		date_default_timezone_set('UTC');
	}

	/**
	 * Get (optional) configuration from an external file
	 *
	 * @since   3.7.3
	 *
	 * @return  \stdClass|null
	 */
	public function getConfiguration()
	{
		$configurationFile = __DIR__ . '/RoboFile.ini';

		if (!file_exists($configurationFile))
		{
			$this->say('No local configuration file');

			return null;
		}

		$configuration = parse_ini_file($configurationFile);

		if ($configuration === false)
		{
			$this->say('Local configuration file is empty or wrong (check is it in correct .ini format');

			return null;
		}

		return json_decode(json_encode($configuration));
	}

	/**
	 * Get the correct CMS root path
	 *
	 * @since   3.7.3
	 *
	 * @return  string
	 */
	private function getTestingPath()
	{
		if (empty($this->configuration->cmsPath))
		{
			return 'test-install';
		}

		if (!file_exists(dirname($this->configuration->cmsPath)))
		{
			$this->say('CMS path written in local configuration does not exists or is not readable');

			return 'test-install';
		}

		return $this->configuration->cmsPath;
	}

	/**
	 * Creates a testing Joomla site for running the tests (use it before run:test)
	 *
	 * @param   bool $useHtaccess (1/0) Rename and enable embedded Joomla .htaccess file
	 *
	 * @since   3.7.3
	 *
	 * @return  void
	 */
	public function createTestingSite($useHtaccess = false)
	{
		// Clean old testing site
		if (is_dir($this->cmsPath))
		{
			try
			{
				$this->taskDeleteDir($this->cmsPath)->run();
			}
			catch (Exception $e)
			{
				// Sorry, we tried :(
				$this->say('Sorry, you will have to delete ' . $this->cmsPath . ' manually.');

				exit(1);
			}
		}

		$exclude = [
			'tests',
			'tests-phpunit',
			'.run',
			'.github',
			'.git',
			'test-install',
			'libraries/vendor/codeception',
			'libraries/vendor/behat',
			'libraries/vendor/joomla-projects',
			'libraries/vendor/consolidation'
		];

		$this->copyJoomla($this->cmsPath, $exclude);

		// Optionally change owner to fix permissions issues
		if (!empty($this->configuration->localUser))
		{
			$this->_exec('chown -R ' . $this->configuration->localUser . ':' . $this->configuration->localUser  . ' ' . $this->cmsPath);
		}

		// Optionally uses Joomla default htaccess file. Used by TravisCI
		if ($useHtaccess == true)
		{
			$this->say('Renaming htaccess.txt to .htaccess');
			$this->_copy('./htaccess.txt', $this->cmsPath . '/.htaccess');
			$this->_exec('sed -e "s,# RewriteBase /,RewriteBase /test-install/joomla-cms,g" -in-place test-install/joomla-cms/.htaccess');
		}
	}

	/**
	 * Copy the Joomla installation excluding folders
	 *
	 * @param   string  $dst      Target folder
	 * @param   array   $exclude  Exclude list of folders
	 *
	 * @throws  Exception
	 *
	 * @since   3.7.3
	 *
	 * @return  void
	 */
	protected function copyJoomla($dst, $exclude = array())
	{
		$dir = @opendir(".");

		if (false === $dir)
		{
			throw new Exception($this, "Cannot open source directory");
		}

		if (!is_dir($dst))
		{
			mkdir($dst, 0755, true);
		}

		while (false !== ($file = readdir($dir)))
		{
			if (in_array($file, $exclude))
			{
				continue;
			}

			if (($file !== '.') && ($file !== '..'))
			{
				$srcFile  = "." . '/' . $file;
				$destFile = $dst . '/' . $file;

				if (is_dir($srcFile))
				{
					try
					{
						$this->_copyDir($srcFile, $destFile);
					}
					catch (Exception $e)
					{
						// Sorry, we tried :(
						$this->say('Sorry, you will have to Skip ' . $srcFile . ' Copy manually.');
					}
				}
				else
				{
					copy($srcFile, $destFile);
				}
			}
		}

		closedir($dir);
	}

	/**
	 * Executes all the Selenium System Tests in a suite on your machine
	 *
	 * @param   array $opts   Array of configuration options:
	 *                        - 'use-htaccess': renames and enable embedded Joomla .htaccess file
	 *                        - 'env': set a specific environment to get configuration from
	 *
	 * @since   3.7.3
	 *
	 * @return  mixed
	 */
	public function runTests($opts = ['use-htaccess' => false, 'env' => 'desktop'])
	{
		$this->say("Running tests");

		$this->createTestingSite($opts['use-htaccess']);

		$this->taskRunSelenium(self::SELENIUM_FOLDER, $this->getWebdriver())->run();

		sleep(3);

		// Make sure to run the build command to generate AcceptanceTester
		if ($this->isWindows())
		{
			$this->_exec('php ' . $this->getWindowsPath($this->vendorPath . 'bin/codecept') . ' build');
			$pathToCodeception = $this->getWindowsPath($this->vendorPath . 'bin/codecept');
		}
		else
		{
			$this->_exec('php ' . $this->vendorPath . 'bin/codecept build');

			$pathToCodeception = $this->vendorPath . 'bin/codecept';
		}

		$this->taskCodecept($pathToCodeception)
			->arg('--steps')
			->arg('--debug')
			->arg('--fail-fast')
			->env($opts['env'])
			->arg($this->testsPath . 'acceptance/install/')
			->run()
			->stopOnFail();

		$this->taskCodecept()
			->arg('--steps')
			->arg('--debug')
			->arg('--fail-fast')
			->env($opts['env'])
			->arg($this->testsPath . '/acceptance/administrator/components/com_menu')
			->run()
			->stopOnFail();

		$this->taskCodecept()
			->arg('--steps')
			->arg('--debug')
			->arg('--fail-fast')
			->env($opts['env'])
			->arg($this->testsPath . '/acceptance/administrator/components/com_users/')
			->run()
			->stopOnFail();
	}

	/**
	 * Executes a specific Selenium System Tests in your machine
	 *
	 * @param   string  $pathToTestFile  Optional name of the test to be run
	 * @param   string  $suite           Optional name of the suite containing the tests, Acceptance by default.
	 *
	 * @since   3.7.3
	 *
	 * @return  void
	 */
	public function runTest($pathToTestFile = null, $suite = 'acceptance')
	{
		$this->taskRunSelenium(self::SELENIUM_FOLDER, $this->getWebdriver());

		// Make sure to run the build command to generate AcceptanceTester
		$path = $this->vendorPath . 'bin/codecept';
		$this->_exec('php ' . $this->isWindows() ? $this->getWindowsPath($path) : $path . ' build');

		if (!$pathToTestFile)
		{
			$this->say('Available tests in the system:');

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$this->testsPath . '/' . $suite,
					RecursiveDirectoryIterator::SKIP_DOTS
				),
				RecursiveIteratorIterator::SELF_FIRST
			);

			$tests = array();
			$i     = 1;

			$iterator->rewind();

			while ($iterator->valid())
			{
				if (strripos($iterator->getSubPathName(), 'cept.php')
					|| strripos($iterator->getSubPathName(), 'cest.php')
					|| strripos($iterator->getSubPathName(), '.feature')
				)
				{
					$this->say('[' . $i . '] ' . $iterator->getSubPathName());

					$tests[$i] = $iterator->getSubPathName();
					$i++;
				}

				$iterator->next();
			}

			$this->say('');
			$testNumber = $this->ask('Type the number of the test in the list that you want to run...');
			$test       = $tests[$testNumber];
		}

		$pathToTestFile = $this->testsPath . '/' . $suite . '/' . $test;

		// Loading the class to display the methods in the class

		// Logic to fetch the class name from the file name
		$fileName = explode("/", $test);

		// If the selected file is cest only then we will give the option to execute individual methods, we don't need this in cept or feature files
		$i = 1;

		if (isset($fileName[1]) && strripos($fileName[1], 'cest'))
		{
			require $this->testsPath . '/' . $suite . '/' . $test;

			$className     = explode(".", $fileName[1]);
			$class_methods = get_class_methods($className[0]);

			$this->say('[' . $i . '] ' . 'All');

			$methods[$i] = 'All';
			$i++;

			foreach ($class_methods as $method_name)
			{
				$reflect = new ReflectionMethod($className[0], $method_name);

				if (!$reflect->isConstructor() && $reflect->isPublic())
				{
					$this->say('[' . $i . '] ' . $method_name);

					$methods[$i] = $method_name;

					$i++;
				}
			}

			$this->say('');
			$methodNumber = $this->ask('Please choose the method in the test that you would want to run...');
			$method       = $methods[$methodNumber];
		}

		if (isset($method) && $method != 'All')
		{
			$pathToTestFile = $pathToTestFile . ':' . $method;
		}

		$testPathCodecept = $this->vendorPath . 'bin/codecept';

		$this->taskCodecept($this->isWindows() ? $this->getWindowsPath($testPathCodecept) : $testPathCodecept)
			->test($pathToTestFile)
			->arg('--steps')
			->arg('--debug')
			->run()
			->stopOnFail();
	}

	/**
	 * Check if local OS is Windows
	 *
	 * @return  bool
	 *
	 * @since   3.7.3
	 */
	private function isWindows()
	{
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	/**
	 * Return the correct path for Windows (needed by CMD)
	 *
	 * @param   string  $path  Linux path
	 *
	 * @return  string
	 *
	 * @since   3.7.3
	 */
	private function getWindowsPath($path)
	{
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}

	/**
	 * Detect the correct driver for selenium
	 *
	 * @return  string the webdriver string to use with selenium
	 *
	 * @since   3.7.3
	 */
	public function getWebdriver()
	{
		$suiteConfig = $this->getSuiteConfig();
		$driver      = $suiteConfig['modules']['config']['JoomlaBrowser']['browser'];

		return $driver;
	}

	/**
	 * Get the suite configuration
	 *
	 * @param   string  $suite  Name of the test suite
	 *
	 * @return  array
	 *
	 * @since   3.7.3
	 */
	private function getSuiteConfig($suite = 'acceptance')
	{
		if (!$this->suiteConfig)
		{
			$this->suiteConfig = Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/libraries/vendor/joomla/test-system/src/' . $suite . '.suite.yml'));
		}

		return $this->suiteConfig;
	}
}
