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

namespace FireHub\Container\Internal;

/**
 * ### Represents a compiled dependency construction plan
 *
 * Defines the statically analyzed constructor parameters required to instantiate a service.
 *
 * The plan contains no resolved service instances and is independent of the active resolution context.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class DependencyPlan {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ConstructorMetadata $metadata <p>
     * Cached constructor reflection metadata.
     * </p>
     * @param list<\FireHub\Container\Internal\ParameterPlan> $parameters <p>
     * Compiled constructor parameter plans.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public ConstructorMetadata $metadata,
        public array $parameters
    ) {}

}