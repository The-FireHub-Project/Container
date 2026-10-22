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

namespace FireHub\Container\Internal;

/**
 * ### Compiles and caches constructor dependency plans
 *
 * Analyzes constructor parameters once per implementation class and caches the resulting dependency plans.
 *
 * Parameter compilation is delegated to the shared parameter plan compiler, allowing constructor and method
 * injection to use identical parameter metadata and validation rules.
 *
 * Registered service availability, contextual bindings, and dependency candidate selection remain dynamic.
 * @since 1.0.0
 *
 * @internal
 */
final class DependencyPlanCache {

    /**
     * ### Cached dependency plans
     * @since 1.0.0
     *
     * @var array<class-string, \FireHub\Container\Internal\DependencyPlan>
     */
    private array $plans = [];

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ConstructorMetadataCache $metadata <p>
     * Shared constructor metadata cache.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlanCompiler $compiler <p>
     * Shared parameter plan compiler responsible for analyzing dependency parameters.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private readonly ConstructorMetadataCache $metadata,
        private readonly ParameterPlanCompiler $compiler
    ) {}

    /**
     * ### Gets a compiled constructor dependency plan
     *
     * Returns a previously compiled dependency plan or creates one by analyzing the constructor parameters of the
     * specified implementation class.
     *
     * Variadic constructor parameters are excluded from dependency injection, preserving existing constructor
     * autowiring behavior.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To retrieve constructor metadata.
     * @uses \FireHub\Container\Internal\ParameterPlanCompiler::compile() To compile constructor parameter metadata.
     *
     * @param class-string $class <p>
     * Service implementation class whose constructor dependency plan should be retrieved.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a constructor parameter contains invalid
     * injection attributes or unsupported dependency metadata.
     *
     * @return \FireHub\Container\Internal\DependencyPlan Compiled constructor dependency plan.
     */
    public function get (string $class):DependencyPlan {

        if (isset($this->plans[$class]))
            return $this->plans[$class];

        $metadata = $this->metadata->get($class);

        $parameters = [];

        foreach ($metadata->parameters as $parameter) {

            if ($parameter->isVariadic())
                continue;

            $parameters[] = $this->compiler->compile($parameter);

        }

        return $this->plans[$class] = new DependencyPlan(
            $metadata,
            $parameters
        );

    }

}