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

use FireHub\Runtime;
use Closure, InvalidArgumentException, ReflectionFunction, ReflectionMethod;

/**
 * ### Invokes callables using dependency injection
 *
 * Executes closures, named functions, instance methods, static methods, and invokable objects by combining
 * explicitly supplied arguments with dependencies resolved through the shared parameter resolver.
 *
 * Positional arguments are associated with their declared parameter positions, while named arguments are
 * associated with their corresponding parameter names.
 *
 * Explicit arguments take precedence over dependency injection. Missing arguments are resolved within the active
 * resolution context, preserving service lifetimes and circular dependency detection.
 *
 * Constructor parameter bindings are excluded from method invocation.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class MethodInvoker2 {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\MethodPlanCache $plans <p>
     * Shared cache containing compiled callable invocation plans.
     * </p>
     * @param \FireHub\Container\Internal\ParameterResolver $parameters <p>
     * Shared parameter resolver responsible for resolving missing callable arguments.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private MethodPlanCache $plans,
        private ParameterResolver $parameters
    ) {}

    /**
     * ### Invokes a callable using dependency injection
     *
     * Retrieves or compiles the callable invocation plan, validates explicit arguments, resolves missing
     * dependencies, and executes the callable.
     *
     * Positional arguments are mapped to declared parameter positions. Named arguments are mapped to their
     * corresponding parameter names. Variadic parameters accept additional positional arguments or an explicitly
     * supplied list of values.
     *
     * Constructor parameter bindings are not applied during invocation.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodInvoker::plan() To retrieve the callable invocation plan.
     * @uses \FireHub\Container\Internal\MethodInvoker::normalizeArguments() To validate explicit arguments.
     * @uses \FireHub\Container\Internal\ParameterResolver::resolve() To resolve missing parameter dependencies.
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for array key existence.
     *
     * @param callable $callback <p>
     * Closure, named function, instance method, static method, or invokable object to execute.
     * </p>
     * @param array<array-key, mixed> $arguments <p>
     * Explicit positional and named arguments supplied to the callable.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @throws \InvalidArgumentException If explicit arguments contain unknown names, invalid positional indexes,
     * duplicate assignments, or invalid variadic values.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a required dependency cannot be
     * resolved.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are
     * eligible.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If a dependency requires an active scope.
     * @throws \FireHub\Container\Exception\ScopeClosedException If the active scope has been closed.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the callable cannot be compiled.
     *
     * @return mixed Value returned by the invoked callable.
     */
    public function invoke (callable $callback, array $arguments, ResolutionContext $context):mixed {

        $plan = $this->plan($callback);

        [$positional, $named] = $this->normalizeArguments(
            $plan,
            $arguments
        );

        $resolved = [];

        foreach ($plan->parameters as $parameter) {

            $reflection = $parameter->parameter;

            $name = $reflection->getName();
            $position = $reflection->getPosition();

            if ($reflection->isVariadic()) {

                if (Runtime\Arr\Access::keyExists($named, $name)) {

                    /** @var list<mixed> $values */
                    $values = $named[$name]; // @phpstan-ignore offsetAccess.notFound

                    foreach ($values as $value)
                        $resolved[] = $value;

                } else {

                    for ($index = $position; Runtime\Arr\Access::keyExists($positional, $index); $index++)
                        $resolved[] = $positional[$index]; // @phpstan-ignore offsetAccess.notFound

                }

                continue;

            }

            if (Runtime\Arr\Access::keyExists($named, $name)) {

                $resolved[] = $named[$name]; // @phpstan-ignore offsetAccess.notFound

                continue;

            }

            if (Runtime\Arr\Access::keyExists($positional, $position)) {

                $resolved[] = $positional[$position]; // @phpstan-ignore offsetAccess.notFound

                continue;

            }

            $resolved[] = $this->parameters->resolve(
                $plan->consumer,
                $parameter,
                $context,
                use_bindings: false
            );

        }

        return $callback(...$resolved);

    }

    /**
     * ### Retrieves a callable invocation plan
     *
     * Determines the callable category and retrieves its compiled invocation plan.
     *
     * Method callables retain their declaring class for contextual dependency resolution. Standalone functions and
     * closures do not have a consumer class.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodPlanCache::method() To retrieve compiled method plans.
     * @uses \FireHub\Container\Internal\MethodPlanCache::function() To retrieve compiled function plans.
     * @uses \FireHub\Runtime\Str\SB\Search::contains() To check for class method syntax.
     * @uses \FireHub\Runtime\DataIs::array() To check if the callable is an array.
     * @uses \FireHub\Runtime\DataIs::object() To check if the callable is an object.
     * @uses \FireHub\Runtime\DataIs::string() To check if the callable is a string.
     *
     * @param callable $callback <p>
     * Callable whose invocation metadata should be retrieved.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the callable cannot be compiled.
     *
     * @return \FireHub\Container\Internal\MethodPlan Compiled callable invocation plan.
     */
    private function plan (callable $callback):MethodPlan {

        if (Runtime\DataIs::array($callback))
            return $this->plans->method(
                new ReflectionMethod($callback[0], $callback[1])
            );

        if (Runtime\DataIs::string($callback) && Runtime\Str\SB\Search::contains('::', $callback))
            return $this->plans->method(
                new ReflectionMethod($callback)
            );

        if (Runtime\DataIs::object($callback) && !$callback instanceof Closure)
            return $this->plans->method(
                new ReflectionMethod($callback, '__invoke')
            );

        if (Runtime\DataIs::string($callback))
            return $this->plans->function(
                new ReflectionFunction($callback)
            );

        return $this->plans->function(
            new ReflectionFunction(Closure::fromCallable($callback))
        );

    }

    /**
     * ### Normalizes explicit callable arguments
     *
     * Separates positional and named arguments while validating their correspondence to the callable signature.
     *
     * Positional arguments must use consecutive integer indexes beginning at zero. Named arguments must reference
     * declared parameter names. Assigning the same parameter through both positional and named arguments is
     * prohibited.
     *
     * A named variadic argument must contain a list of values.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\DataIs::int() To check if the argument is an integer.
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for array key existence.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the callable has variadic parameters.
     * @uses \FireHub\Runtime\Arr\Inspection::inArray() To check if a value is in a list.
     * @uses \FireHub\Runtime\Arr\Inspection::isList() To check if a value is a list.
     *
     * @param \FireHub\Container\Internal\MethodPlan $plan <p>
     * Compiled callable invocation plan used to validate parameter names and positions.
     * </p>
     * @param array<array-key, mixed> $arguments <p>
     * Explicit positional and named arguments to normalize.
     * </p>
     *
     * @throws \InvalidArgumentException If an argument name or position is invalid, a parameter is assigned more
     * than once, or a named variadic argument does not contain a list.
     *
     * @return array{
     *     0: array<int, mixed>,
     *     1: array<string, mixed>
     * } Normalized positional and named argument collections.
     */
    private function normalizeArguments (MethodPlan $plan, array $arguments):array {

        $positional = [];
        $named = [];

        $names = [];
        $variadic = null;

        foreach ($plan->parameters as $parameter) {

            $reflection = $parameter->parameter;

            $names[$reflection->getName()] = $reflection->getPosition();

            if ($reflection->isVariadic())
                $variadic = $reflection;

        }

        $next = 0;
        $named_started = false;

        foreach ($arguments as $key => $value) {

            if (Runtime\DataIs::int($key)) {

                if ($named_started)
                    throw new InvalidArgumentException(
                        'Positional arguments cannot follow named arguments.'
                    );

                if ($key !== $next)
                    throw new InvalidArgumentException(
                        'Positional argument indexes must be consecutive and start at zero.'
                    );

                $positional[$key] = $value;

                $next++;

                continue;

            }

            $named_started = true;

            if (!Runtime\Arr\Access::keyExists($names, $key))
                throw new InvalidArgumentException(
                    "Unknown callable argument: {$key}."
                );

            $named[$key] = $value;

        }

        $count = Runtime\Arr\Inspection::count($plan->parameters);

        if ($variadic === null && Runtime\Arr\Inspection::count($positional) > $count)
            throw new InvalidArgumentException(
                'Too many positional arguments were supplied.'
            );

        foreach ($named as $name => $value) {

            $position = $names[$name]; // @phpstan-ignore offsetAccess.notFound

            if (Runtime\Arr\Access::keyExists($positional, $position))
                throw new InvalidArgumentException(
                    "Callable argument '{$name}' was supplied more than once."
                );

            if (
                $variadic !== null
                && $name === $variadic->getName()
            ) {

                if (!Runtime\DataIs::array($value) || !Runtime\Arr\Inspection::isList($value))
                    throw new InvalidArgumentException(
                        "Named variadic argument '{$name}' must be a list."
                    );

                if (Runtime\Arr\Inspection::count($positional) > $position)
                    throw new InvalidArgumentException(
                        "Variadic argument '{$name}' cannot be supplied both positionally and by name."
                    );

            }

        }

        return [$positional, $named];

    }

}