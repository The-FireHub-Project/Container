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
 * ### Indicates failure during service resource disposal
 *
 * Thrown when one or more disposable service instances fail to release their resources.
 *
 * The exception preserves all encountered disposal failures while exposing the first failure as the previous
 * exception.
 * @since 1.0.0
 */
final class ServiceDisposalException extends RuntimeException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'Failed to dispose service';

}