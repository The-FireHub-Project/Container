<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.1
 * @package Container
 */

namespace FireHub\Container\Exception;

use FireHub\Core\Exception\Runtime\System\InvariantException;

/**
 * ### Thrown when an unsupported service definition is encountered
 *
 * Indicates that the container cannot process a registered service definition because its type is not supported
 * by the dependency resolver.
 * @since 1.0.0
 */
final class UnsupportedDefinitionException extends InvariantException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Unsupported service definition';

}