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
 * ### Thrown when a contextual dependency binding is already registered
 *
 * Indicates that a contextual dependency registration would violate the registry invariant requiring each
 * consumer and dependency combination to have exactly one associated implementation.
 * @since 1.0.0
 */
final class DuplicateContextualBindingException extends InvariantException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'A contextual binding for the specified consumer and dependency is already registered';

}