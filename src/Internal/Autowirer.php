<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.5
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Container\Exception\ServiceInstantiationException;

/**
 * ### Constructs services using compiled dependency plans
 *
 * Executes cached constructor dependency plans to instantiate services using the shared parameter resolver.
 *
 * Static constructor analysis is performed by the dependency plan cache, while parameter bindings, contextual
 * dependencies, service availability, and candidate selection are evaluated dynamically during construction.
 *
 * Constructor dependencies are resolved within the active resolution context, preserving service lifetimes and
 * circular dependency detection.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Autowirer {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\DependencyPlanCache $plans <p>
     * Shared dependency plan cache containing compiled constructor dependency information.
     * </p>
     * @param \FireHub\Container\Internal\ParameterResolver $parameters <p>
     * Shared parameter resolver responsible for resolving constructor dependencies.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private DependencyPlanCache $plans,
        private ParameterResolver $parameters
    ) {}

    /**
     * ### Constructs a service using its compiled dependency plan
     *
     * Retrieves the compiled constructor dependency plan and creates a new instance of the specified implementation
     * class.
     *
     * Classes without constructors are instantiated directly. For classes with constructors, each compiled parameter
     * is resolved through the shared parameter resolver using the active dependency resolution context.
     *
     * The implementation class must be instantiable. Abstract classes and interfaces cannot be constructed.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\DependencyPlanCache::get() To retrieve the compiled constructor dependency
     * plan.
     * @uses \FireHub\Container\Internal\ParameterResolver::resolve() To resolve constructor parameter dependencies.
     *
     * @param class-string $class <p>
     * Concrete service implementation class to instantiate.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context used to resolve constructor dependencies.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the implementation class is not
     * instantiable or a required constructor dependency cannot be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If a required dependency is not registered.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are
     * eligible.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If a dependency requires an active scope.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If a resolved dependency does not satisfy
     * its registered service type.
     *
     * @return object Newly constructed service instance.
     */
    public function construct (string $class, ResolutionContext $context):object {

        $plan = $this->plans->get($class);

        $metadata = $plan->metadata;

        if (!$metadata->instantiable)
            throw new ServiceInstantiationException;

        if (!$metadata->hasConstructor)
            return $metadata->reflection->newInstance();

        $arguments = [];

        foreach ($plan->parameters as $parameter)
            $arguments[] = $this->parameters->resolve(
                $class,
                $parameter,
                $context
            );

        return $metadata->reflection->newInstanceArgs($arguments);

    }

}