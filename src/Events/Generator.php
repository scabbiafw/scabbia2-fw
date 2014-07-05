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

namespace Scabbia\Events;

use Scabbia\Events\Events;
use Scabbia\Framework\Core;
use Scabbia\Generators\GeneratorBase;
use Scabbia\Helpers\FileSystem;

/**
 * Generator
 *
 * @package     Scabbia\Events
 * @author      Eser Ozvataf <eser@sent.com>
 * @since       2.0.0
 */
class Generator extends GeneratorBase
{
    /** @type array $annotations set of annotations */
    public $annotations = [
        "event" => ["format" => "yaml"]
    ];


    /**
     * Initializes a generator
     *
     * @param mixed  $uApplicationConfig application config
     * @param string $uOutputPath        output path
     *
     * @return Generator
     */
    public function __construct($uApplicationConfig, $uOutputPath)
    {
        parent::__construct($uApplicationConfig, $uOutputPath);
    }

    /**
     * Processes a file
     *
     * @param string $uPath         file path
     * @param string $uFileContents contents of file
     * @param string $uTokens       tokens extracted by tokenizer
     *
     * @return void
     */
    public function processFile($uPath, $uFileContents, $uTokens)
    {
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
        $tEvents = new Events();

        foreach ($uAnnotations as $tClassKey => $tClass) {
            foreach ($tClass["staticMethods"] as $tMethodKey => $tMethod) {
                if (!isset($tMethod["event"])) {
                    continue;
                }

                foreach ($tMethod["event"] as $tEvent) {
                    $tEvents->register(
                        $tEvent["on"],
                        [$tClassKey, $tMethodKey],
                        null,
                        isset($tEvent["priority"]) ? $tEvent["priority"] : null
                    );
                }
            }
        }

        FileSystem::writePhpFile(Core::translateVariables($this->outputPath . "/events.php"), $tEvents->events);
    }

    /**
     * Finalizes generator
     *
     * @return void
     */
    public function finalize()
    {
    }
}
