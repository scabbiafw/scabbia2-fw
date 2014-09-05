<?php
/**
 * Scabbia2 PHP Framework
 * http://www.scabbiafw.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link        http://github.com/scabbiafw/scabbia2 for the canonical source repository
 * @copyright   2010-2013 Scabbia Framework Organization. (http://www.scabbiafw.com/)
 * @license     http://www.apache.org/licenses/LICENSE-2.0 - Apache License, Version 2.0
 */

namespace Scabbia\Framework;

use Scabbia\Framework\ApplicationBase;
use Scabbia\Helpers\FileSystem;
use Scabbia\Config\Config;
use Scabbia\Loaders\Loader;

/**
 * Core framework functionality
 *
 * @package     Scabbia\Framework
 * @author      Eser Ozvataf <eser@sent.com>
 * @since       1.0.0
 */
class Core
{
    /** @type object $loader the instance of the autoloader class */
    public static $loader = null;
    /** @type string $basepath the base directory which framework runs in */
    public static $basepath = null;
    /** @type array $variable array of framework variables */
    public static $variables = [];
    /** @type array $variablesPlaceholderCache cache for framework variables which will be used for translation */
    public static $variablesPlaceholderCache = [[], []];
    /** @type Config project configuration */
    public static $projectConfiguration = null;
    /** @type array $runningApplications array of running applications */
    public static $runningApplications = [];


    // MD ## Core::init method
    /**
     * Initializes the framework to be ready to boot
     *
     * @param Loader $uLoader The instance of the autoloader class
     *
     * @return void
     */
    public static function init(Loader $uLoader)
    {
        // MD set default encoding to UTF-8
        mb_internal_encoding("UTF-8");

        // MD assign autoloader to Core::$loader
        self::$loader = $uLoader;

        // MD determine basepath
        if (self::$basepath === null) {
            $tScriptDirectory = pathinfo($_SERVER["SCRIPT_FILENAME"], PATHINFO_DIRNAME);

            if ($tScriptDirectory !== ".") {
                self::$basepath = FileSystem::combinePaths(getcwd(), $tScriptDirectory);
            } else {
                self::$basepath = getcwd();
            }

            self::$variables["basepath"] = &self::$basepath;
        }

        // MD determine environment variables
        // secure
        if (isset($_SERVER["HTTPS"]) &&
            ((string)$_SERVER["HTTPS"] === "1" || strcasecmp($_SERVER["HTTPS"], "on") === 0)) {
            self::$variables["secure"] = true;
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https") {
            self::$variables["secure"] = true;
        } else {
            self::$variables["secure"] = false;
        }

        // protocol
        if (PHP_SAPI === "cli") {
            self::$variables["protocol"] = "CLI";
        } elseif (isset($_SERVER["SERVER_PROTOCOL"]) && $_SERVER["SERVER_PROTOCOL"] === "HTTP/1.0") {
            self::$variables["protocol"] = "HTTP/1.0";
        } else {
            self::$variables["protocol"] = "HTTP/1.1";
        }

        // host
        if (isset($_SERVER["HTTP_HOST"]) && strlen($_SERVER["HTTP_HOST"]) > 0) {
            self::$variables["host"] = $_SERVER["HTTP_HOST"];
        } else {
            if (isset($_SERVER["SERVER_NAME"])) {
                self::$variables["host"] = $_SERVER["SERVER_NAME"];
            } elseif (isset($_SERVER["SERVER_ADDR"])) {
                self::$variables["host"] = $_SERVER["SERVER_ADDR"];
            } elseif (isset($_SERVER["LOCAL_ADDR"])) {
                self::$variables["host"] = $_SERVER["LOCAL_ADDR"];
            } else {
                self::$variables["host"] = "localhost";
            }

            if (isset($_SERVER["SERVER_PORT"])) {
                if (self::$https) {
                    if ($_SERVER["SERVER_PORT"] !== "443") {
                        self::$variables["host"] .= $_SERVER["SERVER_PORT"];
                    }
                } else {
                    if ($_SERVER["SERVER_PORT"] !== "80") {
                        self::$variables["host"] .= $_SERVER["SERVER_PORT"];
                    }
                }
            }
        }

        // os
        if (strncasecmp(PHP_OS, "WIN", 3) === 0) {
            self::$variables["os"] = "windows";
        } else {
            self::$variables["os"] = "*nix";
        }

        self::updateVariablesCache();
    }

    // MD ## Core::loadProject method
    /**
     * Loads the project file
     *
     * @param string $uProjectConfigPath The path of project configuration file
     *
     * @return void
     */
    public static function loadProject($uProjectConfigPath)
    {
        // MD create a new project configuration stack if it does not exist
        if (self::$projectConfiguration === null) {
            self::$projectConfiguration = new Config();
        }

        // MD add configuration file to project configuration stack
        self::$projectConfiguration->add(
            FileSystem::combinePaths(Core::$basepath, self::translateVariables($uProjectConfigPath))
        );
    }

    // MD ## Core::pickApplication method
    /**
     * Picks the best suited application
     *
     * @return void
     */
    public static function pickApplication()
    {
        // MD loop through application definitions in project configuration
        // test cases for applications, and bind configuration to app
        foreach (self::$projectConfiguration->get() as $tApplicationKey => $tApplicationConfig) {
            // TODO is sanitizing $tApplicationKey needed for paths?
            $tTargetApplication = $tApplicationKey;

            // MD - test conditions for each application definition
            if (isset($tApplicationConfig["tests"])) {
                foreach ($tApplicationConfig["tests"] as $tApplicationTest) {
                    $tSubject = self::translateVariables($tApplicationTest[0]);
                    if (!preg_match($tApplicationTest[1], $tSubject)) {
                        $tTargetApplication = false;
                        break;
                    }
                }
            }

            // MD - if selected application fits all test conditions, run it
            if ($tTargetApplication !== false) {
                $tApplicationWritablePath = self::$basepath . "/writable/generated/app.{$tTargetApplication}";
                self::runApplication($tApplicationConfig, $tApplicationWritablePath);
            }
        }
    }

    // MD ## Core::runApplication method
    /**
     * Runs an application
     *
     * @param mixed  $uApplicationConfig the application configuration
     * @param string $uWritablePath      writable output folder
     *
     * @return void
     */
    public static function runApplication($uApplicationConfig, $uWritablePath)
    {
        // MD include compilation file for the application
        // FIXME is it needed to be loaded before Core and ApplicationBase?
        require "{$uWritablePath}/compiled.php";

        // MD add configuration entries too
        $uApplicationConfig += require "{$uWritablePath}/unified-config.php";

        // MD push framework variables to undo application's own variable definitions
        self::pushSourcePaths($uApplicationConfig);
        self::$runningApplications[] = [ApplicationBase::$current, self::$variables];

        // MD construct the application class
        $tApplicationType = $uApplicationConfig["type"];
        $tApplication = new $tApplicationType ($uApplicationConfig, $uWritablePath);

        ApplicationBase::$current = $tApplication;
        $tApplication->generateRequestFromGlobals();

        // MD pop framework variables
        list(ApplicationBase::$current, self::$variables) = array_pop(self::$runningApplications);
        self::popSourcePaths();
    }

    /**
     * Pushes source paths into loader's stack
     *
     * @param mixed  $uConfig the configuration
     *
     * @return void
     */
    public static function pushSourcePaths($uConfig)
    {
        if (!isset($uConfig["autoload"])) {
            return;
        }

        if (isset($uConfig["codepools"])) {
            $tCodepools = (array)$uConfig["codepools"];
        } else {
            $tCodepools = ["local", "core"];
        }

        self::$loader->push();

        foreach ($uConfig["autoload"] as $tNamespace => $tPaths) {
            // FIXME in_array may be placed here to check for paths against duplication, but it's a rare case.
            $tTranslatedPaths = [];
            foreach ((array)$tPaths as $tPath) {
                foreach ($tCodepools as $tCodepool) {
                    $tTranslatedPaths[] = str_replace(
                        "{codepool}",
                        $tCodepool,
                        self::translateVariables($tPath)
                    );
                }
            }

            if ($tNamespace === "default") {
                self::$loader->setPsr4(false, $tTranslatedPaths);
            } else {
                self::$loader->setPsr4($tNamespace, $tTranslatedPaths);
            }
        }
    }

    /**
     * Pops source paths in loader's stack
     *
     * @return void
     */
    public static function popSourcePaths()
    {
        self::$loader->pop();
    }

    /**
     * Updates the cache of variables which will be used in translateVariables method
     *
     * @return void
     */
    public static function updateVariablesCache()
    {
        self::$variablesPlaceholderCache[0] = [];
        self::$variablesPlaceholderCache[1] = [];

        foreach (self::$variables as $tKey => $tValue) {
            if (!is_scalar($tValue)) {
                continue;
            }

            self::$variablesPlaceholderCache[0][] = "{" . $tKey . "}";
            self::$variablesPlaceholderCache[1][] = $tValue;
        }
    }

    /**
     * Replaces placeholders in given string with framework-variables
     *
     * @param string $uInput the string with placeholders
     *
     * @return string translated string
     */
    public static function translateVariables($uInput)
    {
        return str_replace(self::$variablesPlaceholderCache[0], self::$variablesPlaceholderCache[1], $uInput);
    }

    /**
     * Gets a resource path using composer loader
     *
     * @param string $uPath the path needed to be resolved by composer loader
     *
     * @return string|false the path if found, false otherwise
     */
    public static function findResource($uPath)
    {
        return self::$loader->findFileWithExtension($uPath, "");
    }

    /**
     * Reads the contents from cache folder as long as it is not expired
     * If the file is expired, invokes callback method and caches output
     *
     * @param string      $uPath         the relative path
     * @param mixed       $uDefaultValue the default value
     * @param array       $uOptions      options
     *
     * @return mixed the result
     */
    public static function cachedRead($uPath, $uDefaultValue, array $uOptions = [])
    {
        $tCacheFile = self::$basepath . "/writable/cache/" . crc32(realpath($uPath));

        return FileSystem::readFromCacheFile(
            $tCacheFile,
            $uDefaultValue,
            $uOptions
        );
    }
}
