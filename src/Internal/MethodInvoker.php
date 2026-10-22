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

use FireHub\Container\Exception\InvalidMethodArgumentException;
use FireHub\Runtime;
use Closure, ReflectionFunction, ReflectionMethod, ReflectionParameter;

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
final readonly class MethodInvoker {

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
     * Retrieves the compiled invocation plan, normalizes explicit arguments, resolves missing dependencies, and
     * executes the callable.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodInvoker::plan() To retrieve the callable invocation plan.
     * @uses \FireHub\Container\Internal\MethodInvoker::normalizeArguments() To normalize explicit arguments.
     * @uses \FireHub\Container\Internal\MethodInvoker::resolveArguments() To resolve callable arguments.
     *
     * @param callable $callback <p>
     * Callable to execute using dependency injection.
     * </p>
     * @param array<array-key, mixed> $arguments <p>
     * Explicit positional and named arguments.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\InvalidMethodArgumentException If explicit arguments are invalid.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter cannot be resolved or the
     * resolved dependency does not satisfy its declared type.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     *
     * @return mixed Value returned by the callable.
     */
    public function invoke (callable $callback, array $arguments, ResolutionContext $context):mixed {

        $plan = $this->plan($callback);

        [$positional, $named] = $this->normalizeArguments($plan, $arguments);

        return $callback(
            ...$this->resolveArguments($plan, $positional, $named, $context)
        );

    }

    /**
     * ### Resolves callable arguments
     *
     * Combines explicit positional and named arguments with automatically resolved dependencies.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodInvoker::resolveParameter() To resolve individual parameters.
     * @uses \FireHub\Container\Internal\MethodInvoker::resolveVariadic() To resolve variadic parameters.
     *
     * @param \FireHub\Container\Internal\MethodPlan $plan <p>
     * Compiled callable invocation plan.
     * </p>
     * @param array<int, mixed> $positional <p>
     * Explicit positional arguments.
     * </p>
     * @param array<string, mixed> $named <p>
     * Explicit named arguments.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter cannot be resolved or the
     * resolved dependency does not satisfy its declared type.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     *
     * @return list<mixed> Resolved callable arguments.
     */
    private function resolveArguments (MethodPlan $plan, array $positional, array $named, ResolutionContext $context):array {

        $resolved = [];

        foreach ($plan->parameters as $parameter) {

            if ($parameter->parameter->isVariadic()) {

                foreach ($this->resolveVariadic($parameter, $positional, $named) as $value)
                    $resolved[] = $value;

                continue;

            }

            $resolved[] = $this->resolveParameter(
                $plan,
                $parameter,
                $positional,
                $named,
                $context
            );

        }

        return $resolved;

    }

    /**
     * ### Resolves an individual callable parameter
     *
     * Returns an explicitly supplied named or positional argument when available. Otherwise, resolves the parameter
     * through the shared dependency resolver.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ParameterResolver::resolve() To resolve missing dependencies.
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for array key existence.
     *
     * @param \FireHub\Container\Internal\MethodPlan $plan <p>
     * Compiled callable invocation plan.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $parameter <p>
     * Compiled parameter metadata.
     * </p>
     * @param array<int, mixed> $positional <p>
     * Explicit positional arguments.
     * </p>
     * @param array<string, mixed> $named <p>
     * Explicit named arguments.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter cannot be resolved or the
     * resolved dependency does not satisfy its declared type.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     *
     * @return mixed Resolved parameter value.
     */
    private function resolveParameter (MethodPlan $plan, ParameterPlan $parameter, array $positional, array $named, ResolutionContext $context):mixed {

        $reflection = $parameter->parameter;

        $name = $reflection->getName();
        $position = $reflection->getPosition();

        if (Runtime\Arr\Access::keyExists($named, $name))
            return $named[$name]; // @phpstan-ignore offsetAccess.notFound

        if (Runtime\Arr\Access::keyExists($positional, $position))
            return $positional[$position]; // @phpstan-ignore offsetAccess.notFound

        return $this->parameters->resolve(
            $plan->consumer,
            $parameter,
            $context,
            use_bindings: false
        );

    }

    /**
     * ### Resolves variadic callable arguments
     *
     * Retrieves variadic values from an explicitly supplied named list or from remaining positional arguments.
     *
     * Variadic parameters are not automatically resolved through dependency injection.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for array key existence.
     *
     * @param \FireHub\Container\Internal\ParameterPlan $parameter <p>
     * Compiled variadic parameter metadata.
     * </p>
     * @param array<int, mixed> $positional <p>
     * Explicit positional arguments.
     * </p>
     * @param array<string, mixed> $named <p>
     * Explicit named arguments.
     * </p>
     *
     * @return list<mixed> Explicit variadic argument values.
     */
    private function resolveVariadic (ParameterPlan $parameter, array $positional, array $named):array {

        $reflection = $parameter->parameter;

        $name = $reflection->getName();

        if (Runtime\Arr\Access::keyExists($named, $name)) {

            /** @var list<mixed> $values */
            $values = $named[$name]; // @phpstan-ignore offsetAccess.notFound

            return $values;

        }

        $values = [];

        for (
            $index = $reflection->getPosition();
            Runtime\Arr\Access::keyExists($positional, $index);
            $index++
        ) $values[] = $positional[$index]; // @phpstan-ignore offsetAccess.notFound

        return $values;

    }

    /**
     * ### Normalizes explicit callable arguments
     *
     * Separates positional and named arguments and delegates signature validation to the argument validator.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodInvoker::validateArguments() To validate argument assignments.
     * @uses \FireHub\Runtime\DataIs::int() To check if the argument is an integer.
     *
     * @param \FireHub\Container\Internal\MethodPlan $plan <p>
     * Compiled callable invocation plan.
     * </p>
     * @param array<array-key, mixed> $arguments <p>
     * Explicit positional and named arguments.
     * </p>
     *
     * @throws \FireHub\Container\Exception\InvalidMethodArgumentException If arguments are invalid.
     *
     * @return array{
     *     0: array<int, mixed>,
     *     1: array<string, mixed>
     * } Normalized argument collections.
     */
    private function normalizeArguments (MethodPlan $plan, array $arguments):array {

        $positional = [];
        $named = [];

        $next = 0;
        $named_started = false;

        foreach ($arguments as $key => $value) {

            if (Runtime\DataIs::int($key)) {

                if ($named_started || $key !== $next)
                    throw new InvalidMethodArgumentException(
                        'Positional arguments must be consecutive and precede named arguments.'
                    );

                $positional[$key] = $value;

                $next++;

                continue;

            }

            $named_started = true;

            $named[$key] = $value;

        }

        $this->validateArguments($plan, $positional, $named);

        return [$positional, $named];

    }

    /**
     * ### Validates explicit callable arguments
     *
     * Ensures positional arguments do not exceed the callable signature and validates each named argument.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\MethodInvoker::validateNamedArgument() To validate named arguments.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the callable has variadic parameters.
     *
     * @param \FireHub\Container\Internal\MethodPlan $plan <p>
     * Compiled callable invocation plan.
     * </p>
     * @param array<int, mixed> $positional <p>
     * Explicit positional arguments.
     * </p>
     * @param array<string, mixed> $named <p>
     * Explicit named arguments.
     * </p>
     *
     * @throws \FireHub\Container\Exception\InvalidMethodArgumentException If argument assignments are invalid.
     *
     * @return void
     */
    private function validateArguments (MethodPlan $plan, array $positional, array $named):void {

        $names = [];
        $variadic = null;

        foreach ($plan->parameters as $parameter) {

            $reflection = $parameter->parameter;

            $names[$reflection->getName()] = $reflection->getPosition();

            if ($reflection->isVariadic())
                $variadic = $reflection;

        }

        if (
            $variadic === null
            && Runtime\Arr\Inspection::count($positional) > Runtime\Arr\Inspection::count($plan->parameters)
        ) throw new InvalidMethodArgumentException(
                'Too many positional arguments were supplied.'
            );

        foreach ($named as $name => $value)
            $this->validateNamedArgument(
                $name,
                $value,
                $names,
                $positional,
                $variadic
            );

    }

    /**
     * ### Validates an explicitly named argument
     *
     * Checks that the argument references an existing parameter, has not yet been supplied positionally, and
     * satisfies variadic argument requirements.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for array key existence.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the callable has variadic parameters.
     * @uses \FireHub\Runtime\Arr\Inspection::isList() To check if a value is a list.
     * @uses \FireHub\Runtime\DataIs::array() To check if the value is an array.
     *
     * @param string $name <p>
     * Explicit argument name.
     * </p>
     * @param mixed $value <p>
     * Explicit argument value.
     * </p>
     * @param array<string, int> $names <p>
     * Declared parameter names mapped to their positions.
     * </p>
     * @param array<int, mixed> $positional <p>
     * Explicit positional arguments.
     * </p>
     * @param null|\ReflectionParameter $variadic <p>
     * Variadic parameter metadata, or null when the callable has no variadic parameter.
     * </p>
     *
     * @throws \FireHub\Container\Exception\InvalidMethodArgumentException If the named argument is invalid.
     *
     * @return void
     */
    private function validateNamedArgument (string $name, mixed $value, array $names, array $positional, ?ReflectionParameter $variadic):void {

        if (!Runtime\Arr\Access::keyExists($names, $name))
            throw new InvalidMethodArgumentException(
                "Unknown callable argument: {$name}."
            );

        $position = $names[$name]; // @phpstan-ignore offsetAccess.notFound

        if (Runtime\Arr\Access::keyExists($positional, $position))
            throw new InvalidMethodArgumentException(
                "Callable argument '{$name}' was supplied more than once."
            );

        if ($variadic === null || $name !== $variadic->getName())
            return;

        if (!Runtime\DataIs::array($value) || !Runtime\Arr\Inspection::isList($value))
            throw new InvalidMethodArgumentException(
                "Named variadic argument '{$name}' must be a list."
            );

        if (Runtime\Arr\Inspection::count($positional) > $position)
            throw new InvalidMethodArgumentException(
                "Variadic argument '{$name}' cannot be supplied both positionally and by name."
            );

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

}