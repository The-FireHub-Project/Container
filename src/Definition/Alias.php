<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.2
 * @package Container
 */

namespace FireHub\Container\Definition;

use FireHub\Container\Definition;

/**
 * ### Defines a service alias
 *
 * Redirects service resolution to another registered service identifier.
 *
 * Aliases do not own service instances or maintain independent lifetimes.
 * @since 1.0.0
 */
final readonly class Alias implements Definition {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param class-string $target <p>
     * Target service identifier.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public string $target
    ) {}

}