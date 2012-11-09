<?php
/**
 * This file is part of The Frood framework.
 * @link https://github.com/Ibmurai/frood
 *
 * @copyright Copyright 2011 Jens Riisom Schultz
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */
/**
 * FroodAutoloader - The Frood autoloader.
 *
 * @category Frood
 * @package  Autoloader
 * @author   Jens Riisom Schultz <ibber_of_crew42@hotmail.com>
 * @author   Bo Thinggaard <akimsko@tnactas.dk>
 */
class FroodAutoloader {
	/** @var array An array of paths to use as the base of autoloading. */
	private $_classPaths;

	/** @var string[] Stores cached classes, as class name => path to class. */
	private static $_classCache = array();

	/** @var string Complete path to cache directory. */
	private static $_cacheDir;
	
	/** @var string[] Store classes that are not found in this autoloader. */
	private $_missed = array();
	
	/** @var string[] Store the file paths for all files in a class path. */
	private $_fileCache = array();

	/**
	 * Construct a new autoloader.
	 * It will automatically register itself.
	 *
	 * @param array $classPaths An array of paths to use as the base of autoloading.
	 */
	public function __construct(array $classPaths) {
		if (self::$_cacheDir === null) {
			self::setCacheDir(dirname(__FILE__) . '/autoloader_cache/');
		}
		
		foreach ($classPaths as $classPath) {
			$this->addClassPath($classPath);
		}

		$this->_register();
	}

	/**
	 * Dynamically add a class path to this autoloader.
	 *
	 * @param string $classPath
	 */
	public function addClassPath($classPath) {
		$this->_classPaths[] = $classPath;
		$this->_fileCache[$classPath] = self::_getFiles($classPath);
		$this->_missed = array();

		self::_loadCache($classPath);
		$this->_validateCache($classPath);
	}
	
	/**
	 * Converts a classpath to a valid filename.
	 * 
	 * @param string $classPath The class path.
	 * 
	 * @return string The classpath as a valid filename.
	 */
	private static function _classPathToFilename($classPath) {
		static $filenames = array();
		
		if (!isset($filenames[$classPath])) {
			$filenames[$classPath] = preg_replace('/[\/\\\: ]/', '_', $classPath);
		}
		
		return $filenames[$classPath];
	}
	
	/**
	 * Set the cache directory for all frood autoloaders.
	 * Will try to create the directory if it doesnt exist.
	 * 
	 * @param string $cacheDir Full path to the caching directory.
	 */
	public static function setCacheDir($cacheDir) {
		if (is_dir($cacheDir) || @mkdir($cacheDir, 0777)) {
			self::$_cacheDir = $cacheDir;
		}
	}
	
	/**
	 * Check if the classes in the cache can still be found at the cached location.
	 * Cleares cache if invalid.
	 * 
	 * @param string $classPath The class path.
	 */
	private function _validateCache($classPath) {
		foreach (self::$_classCache[$classPath] as $class) {
			if (!in_array($class, $this->_fileCache[$classPath])) {
				self::_clearCache($classPath);
				break;
			}
		}
	}

	/**
	 * Load the class cache for a class path.
	 * 
	 * @param string $classPath The class path.
	 */
	private static function _loadCache($classPath) {
		$filename = self::_classPathToFilename($classPath);
		self::$_classCache[$classPath] = (self::$_cacheDir && ($classCache = @file_get_contents(self::$_cacheDir . $filename)) && ($classCache = @unserialize($classCache))) ? $classCache : array();
	}

	/**
	 * Persist the class cache for a class path.
	 * 
	 * @param string $classPath The class path.
	 *
	 * @return boolean Success.
	 */
	private static function _persistCache($classPath) {
		if (!self::$_classCache[$classPath]) {
			return;
		}
		$filename = self::_classPathToFilename($classPath);
		return self::$_cacheDir ? @file_put_contents(self::$_cacheDir . $filename, @serialize(self::$_classCache[$classPath]), LOCK_EX) : false;
	}
	
	/**
	 * Cleares the file and static memory cache.
	 * 
	 * @param string $classPath The class path.
	 * 
	 * @return boolean Success.
	 */
	private static function _clearCache($classPath) {
		$filename = self::_classPathToFilename($classPath);
		self::$_classCache[$classPath] = array();
		return self::$_cacheDir ? @file_put_contents(self::$_cacheDir . $filename, '', LOCK_EX) : false;
	}
	
	/**
	 * Get a cached path to a class.
	 * 
	 * @param string $name The class name.
	 * 
	 * @return string|null The cached path to the class.
	 */
	private static function _checkCache($name) {
		foreach (self::$_classCache as $classes) {
			if (isset($classes[$name])) {
				return $classes[$name];
			}
		}
	}

	/**
	 * Attempts to load the given class.
	 *
	 * @param string $name The name of the class to load.
	 */
	public function autoload($name) {
		if (isset($this->_missed[$name])) {
			return;
		}

		if (($path = self::_checkCache($name)) || ($path = $this->_classNameToPath($name))) {
			include_once $path;
			return;
		}
		
		$this->_missed[$name] = true;
	}

	/**
	 * Unregister the autoloader. Persist and clean memory cache.
	 *
	 * @throws RumtimeException If the autoloader could not be unregistered.
	 */
	public function unregister() {
		if (!spl_autoload_unregister(array($this, 'autoload'))) {
			throw new RumtimeException('Could not unregister.');
		}
		
		foreach ($this->_classPaths as $classPath) {
			self::_persistCache($classPath);
			unset(self::$_classCache[$classPath]);
		}
	}

	/**
	 * Register the autoloader.
	 */
	private function _register() {
		if (false === spl_autoload_functions()) {
			if (function_exists('__autoload')) {
				spl_autoload_register('__autoload', false);
			}
		}

		spl_autoload_register(array($this, 'autoload'));
	}
	
	/**
	 * Convert a class name to a path to a file containing the class
	 * definition.
	 * Used by the autoloader.
	 *
	 * @param string $name The name of the class.
	 *
	 * @return null|string A full path or null if no suitable file could be found.
	 */
	private function _classNameToPath($name) {
		if (preg_match('/^((?:[A-Z][a-z0-9]*)+)$/', $name)) {
			// Build a regular expression matching the end of the filepaths to accept...
			$regex = '/[\/\\\][a-z]+[A-Za-z_-]*[\/\\\]' . substr($name, 0, 1) . preg_replace('/([A-Z])/', '[\/\\\\\\]?\\1', substr($name, 1)) . '\.php$/';

			foreach ($this->_classPaths as $classPath) {
				if ($path = $this->_searchFiles($classPath, $regex)) {
					self::$_classCache[$classPath][$name] = $path;
					return $path;
				}
			}
		}

		return null;
	}
	
	/**
	 * Internally used method. Used by _classNameToPath.
	 *
	 * @param string $classPath The directory to search in.
	 * @param string $regex     The regular expression to match on the full path.
	 *
	 * @return null|string null if no match was found.
	 */
	private function _searchFiles($classPath, $regex) {
		foreach ($this->_fileCache[$classPath] as $filePath) {
			if (preg_match($regex, $filePath)) {
				return $filePath;
			}
		}
	}
	
	/**
	 * Internally used method. Used by addClassPath to cache all files in the newly added class path.
	 *
	 * @param string $classPath The directory to search in.
	 * @param array  &$files    The array to store filepaths in.
	 *
	 * @return array The file paths.
	 */
	private static function _getFiles($classPath, array &$files = array()) {
		if (!is_dir($classPath)) {
			return $files;
		}

		$iterator = new DirectoryIterator($classPath);

		foreach ($iterator as $finfo) {
			if (substr($finfo->getBasename(), 0, 1) != '.') {
				if ($finfo->isFile()) {
					$files[] = $finfo->getPathname();
				} else if ($finfo->isDir()) {
					self::_getFiles($finfo->getPathname(), $files);
				}
			}
		}

		return $files;
	}
	
	/**
	 * Persist memory cache for known class paths.
	 */
	public function __destruct() {
		foreach ($this->_classPaths as $classPath) {
			self::_persistCache($classPath);
		}
	}
}
