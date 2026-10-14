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
use Closure;

/**
 * ### Defines a factory-based service
 *
 * Describes a service created by invoking a factory function.
 *
 * The factory receives the active dependency resolver, allowing dependencies to be resolved within the appropriate
 * execution scope.
 * @since 1.0.0
 */
final readonly class Factory implements Service {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param Closure(\FireHub\Core\Boundary\Resolver):object $factory <p>
     * Service factory.
     * </p>
     * @param \FireHub\Core\Meta\Enum\Container\Lifetime $lifetime <p>
     * Service lifetime.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public Closure $factory,
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