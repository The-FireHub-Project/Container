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
 * ### Thrown when a service identifier is already registered
 *
 * Indicates that a service registration would violate the registry invariant requiring each service identifier
 * to have exactly one associated definition.
 * @since 1.0.0
 */
final class DuplicateServiceException extends InvariantException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'A service with the specified identifier is already registered';

}