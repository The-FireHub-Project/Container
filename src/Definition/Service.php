<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container
 */

namespace FireHub\Container\Definition;

use FireHub\Core\Meta\Enum\Container\Lifetime;
use FireHub\Container\Definition;

/**
 * ### Defines a service definition with a managed lifetime
 *
 * Represents a registered service definition that specifies how long its resolved instance should be retained
 * and reused by the dependency injection container.
 *
 * Service definitions provide lifetime metadata without prescribing how service instances are constructed,
 * cached, or resolved.
 * @since 1.0.0
 */
interface Service extends Definition {

    /**
     * ### Gets the service lifetime
     *
     * Returns the lifetime associated with this service definition, determining whether resolved instances are
     * shared across the container, isolated within individual scopes, or created for each resolution request.
     * @since 1.0.0
     *
     * @return \FireHub\Core\Meta\Enum\Container\Lifetime Configured service lifetime.
     */
    public function lifetime ():Lifetime;

}