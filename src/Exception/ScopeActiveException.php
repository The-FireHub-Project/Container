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
 * ### Thrown when closing a scope during active service resolution
 * @since 1.0.0
 */
final class ScopeActiveException extends RuntimeException {

    protected const string DEFAULT_MESSAGE = 'Cannot close a dependency injection scope while service resolution is active.';

}