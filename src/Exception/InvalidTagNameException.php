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
 * ### Thrown when a service tag name is invalid
 *
 * Indicates that a service tag name is empty or contains only whitespace characters.
 * @since 1.0.0
 */
final class InvalidTagNameException extends InvariantException {

    /**
     * ### Default exception message
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Service tag name must not be empty or contain only whitespace';

}