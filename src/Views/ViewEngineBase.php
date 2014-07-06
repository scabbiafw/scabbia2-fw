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

namespace Scabbia\Views;

/**
 * ViewEngineBase
 *
 * @package     Scabbia\Views
 * @author      Eser Ozvataf <eser@sent.com>
 * @since       2.0.0
 *
 * @todo compile
 */
class ViewEngineBase
{
    /**
     * Initializes a view engine
     *
     * @return ViewEngineBase
     */
    public function __construct()
    {
    }

    /**
     * Renders plain PHP file for using them as a template format
     *
     * @param string $tTemplatePath path of the template file
     * @param string $tTemplateFile filename of the template file
     * @param mixed  $uModel        model object
     *
     * @return void
     */
    public function render($tTemplatePath, $tTemplateFile, $uModel = null)
    {
        if ($uModel !== null && is_array($uModel)) {
            extract($uModel, EXTR_SKIP | EXTR_REFS);
        }

        include "{$tTemplatePath}{$tTemplateFile}";
    }
}
