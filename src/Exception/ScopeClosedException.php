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

use FireHub\Core\Exception\RuntimeException;

/**
 * ### Thrown when accessing a closed dependency injection scope
 *
 * Indicates that an operation was attempted on a scope that has already been closed and is no longer available for
 * service resolution.
 * @since 1.0.0
 */
final class ScopeClosedException extends RuntimeException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Cannot access a closed dependency injection scope.';

}