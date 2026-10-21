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
 * ### Defines a class-based service
 *
 * Describes a service whose instance is constructed from a concrete class.
 *
 * The container may resolve constructor dependencies automatically.
 * @since 1.0.0
 *
 * @template TService of object
 */
final readonly class Cls implements Service {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param class-string<TService> $class <p>
     * Concrete implementation class.
     * </p>
     * @param \FireHub\Core\Meta\Enum\Container\Lifetime $lifetime <p>
     * Service lifetime.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public string $class,
        private Lifetime $lifetime = Lifetime::TRANSIENT
    ) {}

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    public function lifetime ():Lifetime {

        return $this->lifetime;

    }

}