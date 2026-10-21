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

use ReflectionClass, ReflectionParameter;

/**
 * ### Represents cached constructor metadata
 *
 * Provides immutable reflection metadata for a service implementation.
 *
 * Stores the reflected class, its insatiability, and constructor parameters without resolving dependencies or
 * creating service instances.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ConstructorMetadata {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param ReflectionClass<object> $reflection <p>
     * Reflected service implementation.
     * </p>
     * @param bool $instantiable <p>
     * Whether the implementation can be instantiated.
     * </p>
     * @param bool $hasConstructor <p>
     * Whether the implementation declares or inherits a constructor.
     * </p>
     * @param list<ReflectionParameter> $parameters <p>
     * Constructor parameters.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public ReflectionClass $reflection,
        public bool $instantiable,
        public bool $hasConstructor,
        public array $parameters
    ) {}

}