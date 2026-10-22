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

use ReflectionFunction, ReflectionFunctionAbstract, ReflectionMethod;

/**
 * ### Compiles and caches callable invocation plans
 *
 * Analyzes callable reflection metadata and compiles dependency parameter plans using the shared parameter
 * compiler.
 *
 * Method plans are cached by their declaring class and method name. Named function caches by function name, while
 * closures are compiled independently to avoid retaining closure instances.
 *
 * Registered service availability, contextual bindings, and dependency candidate selection remain dynamic.
 * @since 1.0.0
 *
 * @internal
 */
final class MethodPlanCache {

    /**
     * ### Cached method invocation plans
     * @since 1.0.0
     *
     * @var array<string, \FireHub\Container\Internal\MethodPlan>
     */
    private array $plans = [];

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ParameterPlanCompiler $compiler <p>
     * Shared parameter plan compiler used to analyze callable parameters.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private readonly ParameterPlanCompiler $compiler
    ) {}

    /**
     * ### Gets a compiled method invocation plan
     *
     * Retrieves a cached invocation plan for the specified method or compiles one when no cached plan exists.
     *
     * The cache key uses the declaring class and canonical method name, allowing inherited methods to reuse their
     * compiled metadata.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodPlanCache::compile() To compile callable reflection metadata.
     *
     * @param \ReflectionMethod $method <p>
     * Reflected instance or static method to analyze.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a parameter contains unsupported
     * injection metadata.
     *
     * @return \FireHub\Container\Internal\MethodPlan Compiled method invocation plan.
     */
    public function method (ReflectionMethod $method):MethodPlan {

        $key = $method->getDeclaringClass()->getName()
            . '::'
            . $method->getName();

        return $this->plans[$key] ??= $this->compile(
            $method,
            $method->getDeclaringClass()->getName()
        );

    }

    /**
     * ### Gets a compiled function invocation plan
     *
     * Retrieves a cached plan for a named function or compiles a new plan for a closure.
     *
     * Closure invocation plans are not stored in the shared cache.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodPlanCache::compile() To compile callable reflection metadata.
     *
     * @param \ReflectionFunction $function <p>
     * Reflected named function or closure to analyze.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a parameter contains unsupported
     * injection metadata.
     *
     * @return \FireHub\Container\Internal\MethodPlan Compiled function invocation plan.
     */
    public function function (ReflectionFunction $function):MethodPlan {

        if ($function->isClosure())
            return $this->compile($function, null);

        $key = 'function:' . $function->getName();

        return $this->plans[$key] ??= $this->compile(
            $function,
            null
        );

    }

    /**
     * ### Compiles callable invocation metadata
     *
     * Analyzes callable parameters and creates an immutable invocation plan.
     *
     * All parameters are retained in their declaration order, including variadic parameters. Variadic argument
     * expansion is handled by the method invoker rather than the parameter compiler.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ParameterPlanCompiler::compile() To compile callable parameter metadata.
     *
     * @param \ReflectionFunctionAbstract $reflection <p>
     * Reflected callable whose parameters should be compiled.
     * </p>
     * @param null|class-string $consumer <p>
     * Declaring class used for contextual dependency resolution, or null for standalone functions and closures.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a parameter contains unsupported
     * injection metadata.
     *
     * @return \FireHub\Container\Internal\MethodPlan Compiled callable invocation plan.
     */
    private function compile (ReflectionFunctionAbstract $reflection, ?string $consumer):MethodPlan {

        $parameters = [];

        foreach ($reflection->getParameters() as $parameter)
            $parameters[] = $this->compiler->compile($parameter);

        return new MethodPlan(
            $reflection,
            $parameters,
            $consumer
        );

    }

}