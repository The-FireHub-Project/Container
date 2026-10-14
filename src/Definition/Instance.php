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

use FireHub\Core\Meta\Enum\Container\Lifetime;

/**
 * ### Defines an existing service instance
 *
 * Represents an already constructed object registered as a singleton service.
 * @since 1.0.0
 */
final readonly class Instance implements Service {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param object $instance <p>
     * Registered service instance.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public object $instance
    ) {}

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    public function lifetime ():Lifetime {

        return Lifetime::SINGLETON;

    }

}