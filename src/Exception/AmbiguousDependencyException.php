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
 * ### Thrown when multiple services match a dependency
 *
 * Indicates that automatic dependency resolution cannot select a unique
 * candidate from multiple eligible service identifiers.
 * @since 1.0.0
 */
final class AmbiguousDependencyException extends RuntimeException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Multiple services match the requested dependency.';

}