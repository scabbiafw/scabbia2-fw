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

namespace Scabbia\Cli;

use Scabbia\Framework\ApplicationBase;

/**
 * Application Implementation for Command Line Interface
 *
 * @package     Scabbia\Cli
 * @author      Eser Ozvataf <eser@sent.com>
 * @since       2.0.0
 */
class Application extends ApplicationBase
{
    /**
     * Initializes an application
     *
     * @param mixed  $uOptions      options
     * @param array  $uPaths        paths include source files
     * @param string $uWritablePath writable output folder
     *
     * @return Application
     */
    public function __construct($uOptions, $uPaths, $uWritablePath)
    {
        parent::__construct($uOptions, $uPaths, $uWritablePath);
    }

    /**
     * Gets request method
     *
     * @return array
     */
    public function getRequestMethod()
    {
        // TODO not implemented
        return null;
    }

    /**
     * Gets request path info
     *
     * @return array
     */
    public function getRequestPathInfo()
    {
        // TODO not implemented
        return null;
    }

    /**
     * Gets query parameters
     *
     * @return array
     */
    public function getQueryParameters()
    {
        // TODO not implemented
        return null;
    }

    /**
     * Gets post parameters
     *
     * @return array
     */
    public function getPostParameters()
    {
        // TODO not implemented
        return null;
    }

    /**
     * Generates request
     *
     * @param string $uMethod          method
     * @param string $uPathInfo        pathinfo
     * @param array  $uQueryParameters query parameters
     * @param array  $uPostParameters  post parameters
     *
     * @return void
     */
    public function generateRequest($uMethod, $uPathInfo, array $uQueryParameters, array $uPostParameters)
    {
        //
    }

    /**
     * Generates request from globals
     *
     * @return void
     */
    public function generateRequestFromGlobals()
    {
        // $this->generateRequest("GET", "home/index", $_GET, $_POST);
        $this->generateRequest(
            $this->getRequestMethod(),
            $this->getRequestPathInfo(),
            $this->getQueryParameters(),
            $this->getPostParameters()
        );
    }
}
