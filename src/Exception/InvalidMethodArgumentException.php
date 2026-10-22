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

use FireHub\Core\Exception\Runtime\InvalidArgumentException;

/**
 * ### Indicates invalid explicit method invocation arguments
 *
 * Thrown when explicitly supplied callable arguments cannot be mapped to the declared parameter signature.
 *
 * This includes unknown parameter names, invalid positional indexes, duplicate parameter assignments, and
 * invalid variadic argument collections.
 * @since 1.0.0
 */
final class InvalidMethodArgumentException extends InvalidArgumentException {

    /**
     * ### Default exception message
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Invalid method argument(s)';

}