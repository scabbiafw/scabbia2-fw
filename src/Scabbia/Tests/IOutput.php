<?php
/**
 * Scabbia2 PHP Framework
 * http://www.scabbiafw.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link        http://github.com/scabbiafw/scabbia2 for the canonical source repository
 * @copyright   Copyright (c) 2010-2013 Scabbia Framework Organization. (http://www.scabbiafw.com/)
 * @license     http://www.apache.org/licenses/LICENSE-2.0 - Apache License, Version 2.0
 */

namespace Scabbia\Tests;

/**
 * Scabbia\Tests: IOutput Interface
 *
 * A small unit test implementation which helps us during the development of
 * Scabbia2 PHP Framework's itself and related production code.
 *
 * @author Eser Ozvataf <eser@sent.com>
 */
interface IOutput
{
    /**
     * Writes given message.
     *
     * @param $uHeading integer size
     * @param $uMessage string  message
     */
    public function writeHeader($uHeading, $uMessage);

    /**
     * Outputs the report in specified representation.
     *
     * @param array $uReport Target report will be printed
     */
    public function export(array $uReport);
}
