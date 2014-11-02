<?php
/**
 * Scabbia2 PHP Framework Code
 * http://www.scabbiafw.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link        http://github.com/scabbiafw/scabbia2-fw for the canonical source repository
 * @copyright   2010-2014 Scabbia Framework Organization. (http://www.scabbiafw.com/)
 * @license     http://www.apache.org/licenses/LICENSE-2.0 - Apache License, Version 2.0
 */

namespace Scabbia\Router;

use Scabbia\Code\TokenStream;
use Scabbia\Framework\Core;
use Scabbia\Generators\GeneratorBase;
use Scabbia\Helpers\FileSystem;
use Scabbia\Router\Router;
use UnexpectedValueException;

/**
 * RouteGenerator
 *
 * @package     Scabbia\Router
 * @author      Eser Ozvataf <eser@sent.com>
 * @since       2.0.0
 *
 * @scabbia-generator
 *
 * Routing related code based on the nikic's FastRoute solution:
 * http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
 */
class RouteGenerator extends GeneratorBase
{
    /** @type string FILTER_VALIDATE_BOOLEAN a symbolic constant for boolean validation */
    const APPROX_CHUNK_SIZE = 10;


    /** @type array $annotations set of annotations */
    public $annotations = [
        "route" => ["format" => "yaml"]
    ];
    /** @type array $staticRoutes set of static routes */
    public $staticRoutes;
    /** @type array $regexToRoutesMap map of variable routes */
    public $regexToRoutesMap;
    /** @type array $namedRoutes map of named routes */
    public $namedRoutes;


    /**
     * Initializes generator
     *
     * @return void
     */
    public function initialize()
    {
        $this->staticRoutes = [];
        $this->regexToRoutesMap = [];
        $this->namedRoutes = [];
    }

    /**
     * Processes set of annotations
     *
     * @param array $uAnnotations annotations
     *
     * @return void
     */
    public function processAnnotations($uAnnotations)
    {
        foreach ($uAnnotations as $tClassKey => $tClass) {
            foreach ($tClass["methods"] as $tMethodKey => $tMethod) {
                if (!isset($tMethod["route"])) {
                    continue;
                }

                foreach ($tMethod["route"] as $tRoute) {
                    foreach ($this->application->config["modules"] as $tModuleKey => $tModuleDefinition) {
                        if (strncmp(
                            $tClassKey,
                            $tModuleDefinition["namespace"],
                            strlen($tModuleDefinition["namespace"])
                        ) !== 0) {
                            continue;
                        }

                        if ($tModuleKey === "front") {
                            $tModulePrefix = "";
                        } else {
                            $tModulePrefix = "/{$tModuleKey}";
                        }

                        $this->addRoute(
                            $tRoute["method"],
                            "{$tModulePrefix}{$tRoute["path"]}",
                            [$tClassKey, $tMethodKey],
                            isset($tRoute["name"]) ? $tRoute["name"] : null
                        );
                    }
                }
            }
        }
    }

    /**
     * Dumps generated data into file
     *
     * @return void
     */
    public function dump()
    {
        FileSystem::writePhpFile(
            Core::translateVariables($this->application->writablePath . "/routes.php"),
            $this->getData()
        );
    }

    /**
     * Adds specified route
     *
     * @param string|array  $uMethods   http methods
     * @param string        $uRoute     route
     * @param callable      $uCallback  callback
     * @param string|null   $uName      name of route
     *
     * @return void
     */
    public function addRoute($uMethods, $uRoute, $uCallback, $uName = null)
    {
        $tRouteData = Router::parse($uRoute);
        $tMethods = (array)$uMethods;

        if (count($tRouteData) === 1 && is_string($tRouteData[0])) {
            $this->addStaticRoute($tMethods, $tRouteData, $uCallback, $uName);
        } else {
            $this->addVariableRoute($tMethods, $tRouteData, $uCallback, $uName);
        }
    }

    /**
     * Adds a static route
     *
     * @param array         $uMethods    http methods
     * @param array         $uRouteData  route data
     * @param callable      $uCallback   callback
     * @param string|null   $uName       name of route
     *
     * @throws UnexpectedValueException if an routing problem occurs
     * @return void
     */
    public function addStaticRoute(array $uMethods, $uRouteData, $uCallback, $uName = null)
    {
        $tRouteStr = $uRouteData[0];

        foreach ($uMethods as $tMethod) {
            if (isset($this->staticRoutes[$tRouteStr][$tMethod])) {
                throw new UnexpectedValueException(sprintf(
                    "Cannot register two routes matching \"%s\" for method \"%s\"",
                    $tRouteStr,
                    $tMethod
                ));
            }
        }

        foreach ($uMethods as $tMethod) {
            foreach ($this->regexToRoutesMap as $tRoutes) {
                if (!isset($tRoutes[$tMethod])) {
                    continue;
                }

                $tRoute = $tRoutes[$tMethod];
                if (preg_match("~^{$tRoute["regex"]}$~", $tRouteStr) === 1) {
                    throw new UnexpectedValueException(sprintf(
                        "Static route \"%s\" is shadowed by previously defined variable route \"%s\" for method \"%s\"",
                        $tRouteStr,
                        $tRoute["regex"],
                        $tMethod
                    ));
                }
            }

            $this->staticRoutes[$tRouteStr][$tMethod] = $uCallback;

            /*
            if ($uName !== null) {
                if (!isset($this->namedRoutes[$tMethod])) {
                    $this->namedRoutes[$tMethod] = [];
                }

                $this->namedRoutes[$tMethod][$uName] = [$tRouteStr, []];
            }
            */
            if ($uName !== null && !isset($this->namedRoutes[$uName])) {
                $this->namedRoutes[$uName] = [$tRouteStr, []];
            }
        }
    }

    /**
     * Adds a variable route
     *
     * @param array         $uMethods    http method
     * @param array         $uRouteData  route data
     * @param callable      $uCallback   callback
     * @param string|null   $uName       name of route
     *
     * @throws UnexpectedValueException if an routing problem occurs
     * @return void
     */
    public function addVariableRoute(array $uMethods, $uRouteData, $uCallback, $uName = null)
    {
        $tRegex = "";
        $tReverseRegex = "";
        $tVariables = [];

        foreach ($uRouteData as $tPart) {
            if (is_string($tPart)) {
                $tRegex .= preg_quote($tPart, "~");
                $tReverseRegex .= preg_quote($tPart, "~");
                continue;
            }

            list($tVariableName, $tRegexPart) = $tPart;

            if (isset($tVariables[$tVariableName])) {
                throw new UnexpectedValueException(sprintf("Cannot use the same placeholder \"%s\" twice", $tVariableName));
            }

            $tVariables[$tVariableName] = $tVariableName;
            $tRegex .= "({$tRegexPart})";
            $tReverseRegex .= "{{$tVariableName}}";
        }

        foreach ($uMethods as $tMethod) {
            if (isset($this->regexToRoutesMap[$tRegex][$tMethod])) {
                throw new UnexpectedValueException(
                    sprintf("Cannot register two routes matching \"%s\" for method \"%s\"", $tRegex, $tMethod)
                );
            }
        }

        foreach ($uMethods as $tMethod) {
            $this->regexToRoutesMap[$tRegex][$tMethod] = [
                "method"    => $tMethod,
                "callback"  => $uCallback,
                "regex"     => $tRegex,
                "variables" => $tVariables
            ];

            /*
            if ($uName !== null) {
                if (!isset($this->namedRoutes[$tMethod])) {
                    $this->namedRoutes[$tMethod] = [];
                }

                $this->namedRoutes[$tMethod][$uName] = [$tRegex, $tVariables];
            }
            */
            if ($uName !== null && !isset($this->namedRoutes[$uName])) {
                $this->namedRoutes[$uName] = [$tReverseRegex, array_values($tVariables)];
            }
        }
    }

    /**
     * Combines all route data in order to return it as a result of generation process
     *
     * @return array data
     */
    public function getData()
    {
        $tRegexToRoutesMapCount = count($this->regexToRoutesMap);

        if ($tRegexToRoutesMapCount === 0) {
            $tVariableRouteData = [];
        } else {
            $tNumParts = max(1, round($tRegexToRoutesMapCount / self::APPROX_CHUNK_SIZE));
            $tChunkSize = ceil($tRegexToRoutesMapCount / $tNumParts);

            $tChunks = array_chunk($this->regexToRoutesMap, $tChunkSize, true);
            $tVariableRouteData = array_map([$this, "processChunk"], $tChunks);
        }

        return [
            "static"   => $this->staticRoutes,
            "variable" => $tVariableRouteData,
            "named"    => $this->namedRoutes
        ];
    }

    /**
     * Splits variable routes into chunks
     *
     * @param array $uRegexToRoutesMap route definitions
     *
     * @return array chunked
     */
    protected function processChunk(array $uRegexToRoutesMap)
    {
        $tRouteMap = [];
        $tRegexes = [];
        $tNumGroups = 0;

        foreach ($uRegexToRoutesMap as $tRegex => $tRoutes) {
            $tFirstRoute = reset($tRoutes);
            $tNumVariables = count($tFirstRoute["variables"]);
            $tNumGroups = max($tNumGroups, $tNumVariables);

            $tRegexes[] = $tRegex . str_repeat("()", $tNumGroups - $tNumVariables);

            foreach ($tRoutes as $tRoute) {
                $tRouteMap[$tNumGroups + 1][$tRoute["method"]] = [$tRoute["callback"], $tRoute["variables"]];
            }

            ++$tNumGroups;
        }

        return [
            "regex"    => "~^(?|" . implode("|", $tRegexes) . ")$~",
            "routeMap" => $tRouteMap
        ];
    }
}
