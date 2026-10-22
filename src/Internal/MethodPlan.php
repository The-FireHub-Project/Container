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

use ReflectionFunctionAbstract;

/**
 * ### Represents a compiled callable invocation plan
 *
 * Stores the reflection metadata and compiled dependency parameters required to invoke a method or closure.
 *
 * The plan contains only static callable information. Explicit arguments, contextual bindings, dependency
 * availability, and service resolution are evaluated dynamically during invocation.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class MethodPlan {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \ReflectionFunctionAbstract $reflection <p>
     * Reflection metadata describing the callable associated with this invocation plan.
     * </p>
     * @param list<\FireHub\Container\Internal\ParameterPlan> $parameters <p>
     * Compiled dependency parameter plans in their original declaration order.
     * </p>
     * @param null|class-string $consumer <p>
     * Declaring class used for contextual dependency resolution, or null for standalone functions and closures.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public ReflectionFunctionAbstract $reflection,
        public array $parameters,
        public ?string $consumer
    ) {}

}