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

namespace FireHub\Container\Validation;

use FireHub\Container\Internal\ParameterBindingRegistry;
use ReflectionClass, ReflectionException, ReflectionIntersectionType, ReflectionNamedType, ReflectionParameter,
    ReflectionType, ReflectionUnionType;

/**
 * ### Validates explicit constructor parameter bindings
 *
 * Performs static validation of configured constructor parameter values without instantiating services.
 *
 * Validates parameter existence and compatibility with declared PHP parameter types, including nullable, union,
 * intersection, and disjunctive normal form types.
 *
 * Validation does not modify bindings or execute application code.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ParameterBinding {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $bindings <p>
     * Registered constructor parameter bindings.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private ParameterBindingRegistry $bindings
    ) {}

    /**
     * ### Validates registered parameter bindings
     *
     * Checks that configured constructor parameters exist and their values satisfy the declared parameter types.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::all() To retrieve registered bindings.
     * @uses \FireHub\Container\Validation\ParameterBinding::findParameter() To find constructor parameters.
     * @uses \FireHub\Container\Validation\ParameterBinding::matchesType() To check value compatibility.
     *
     * @return list<\FireHub\Container\Validation\Issue> Detected validation issues.
     */
    public function validate ():array {

        $issues = [];

        foreach ($this->bindings->all() as $class => $bindings) {

            try {

                $reflection = new ReflectionClass($class);

            } catch (ReflectionException) { // @phpstan-ignore catch.neverThrown

                $issues[] = new Issue(
                    IssueType::INVALID_PARAMETER_BINDING,
                    Severity::ERROR,
                    $class,
                    "Parameter bindings reference an unknown class '$class'."
                );

                continue;

            }

            $constructor = $reflection->getConstructor();

            foreach ($bindings as $name => $value) {

                $parameter = $constructor !== null
                    ? $this->findParameter($constructor->getParameters(), $name)
                    : null;

                if ($parameter === null) {

                    $issues[] = new Issue(
                        IssueType::INVALID_PARAMETER_BINDING,
                        Severity::ERROR,
                        $class,
                        "Constructor parameter '$name' does not exist in '$class'."
                    );

                    continue;

                }

                $type = $parameter->getType();

                if (
                    $type !== null
                    && !$this->matchesType($parameter, $type, $value)
                ) {

                    $issues[] = new Issue(
                        IssueType::INVALID_PARAMETER_BINDING,
                        Severity::ERROR,
                        $class,
                        "Bound value for parameter '$name' of '$class' is incompatible with type '$type'."
                    );

                }

            }

        }

        return $issues;

    }

    /**
     * ### Finds a constructor parameter
     * @since 1.0.0
     *
     * @param list<ReflectionParameter> $parameters <p>
     * Constructor parameters.
     * </p>
     * @param non-empty-string $name <p>
     * Parameter name.
     * </p>
     *
     * @return null|ReflectionParameter Matching parameter.
     */
    private function findParameter (array $parameters, string $name):?ReflectionParameter {

        foreach ($parameters as $parameter)
            if ($parameter->getName() === $name)
                return $parameter;

        return null;

    }

    /**
     * ### Checks parameter value compatibility
     *
     * Evaluates whether an explicitly bound value satisfies the declared PHP parameter type.
     *
     * Union types require at least one matching member, while intersection types require every member to match.
     * Nested combinations are evaluated recursively to support disjunctive normal form types.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\ParameterBinding::matchesNamedType() To validate named types.
     *
     * @param \ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param \ReflectionType $type <p>
     * Declared parameter type.
     * </p>
     * @param mixed $value <p>
     * Explicitly bound parameter value.
     * </p>
     *
     * @return bool True if the value satisfies the declared type.
     */
    private function matchesType (ReflectionParameter $parameter, ReflectionType $type, mixed $value):bool {

        if ($value === null)
            return $type->allowsNull();

        if ($type instanceof ReflectionUnionType) {

            foreach ($type->getTypes() as $member)
                if ($this->matchesType($parameter, $member, $value))
                    return true;

            return false;

        }

        if ($type instanceof ReflectionIntersectionType) {

            foreach ($type->getTypes() as $member)
                if (!$this->matchesType($parameter, $member, $value))
                    return false;

            return true;

        }

        return $type instanceof ReflectionNamedType
            && $this->matchesNamedType($parameter, $type, $value);

    }

    /**
     * ### Checks named parameter type compatibility
     *
     * Validates scalar, built-in, and class-based parameter types against an explicitly configured value.
     *
     * Relative class types are resolved against the class declaring the constructor parameter.
     * @since 1.0.0
     *
     * @param \ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param \ReflectionNamedType $type <p>
     * Declared named type.
     * </p>
     * @param mixed $value <p>
     * Explicitly bound parameter value.
     * </p>
     *
     * @return bool True if the value satisfies the named type.
     */
    private function matchesNamedType (ReflectionParameter $parameter, ReflectionNamedType $type, mixed $value):bool {

        $name = $type->getName();

        if (!$type->isBuiltin()) {

            $declaring_class = $parameter->getDeclaringClass();
            $parent = $declaring_class?->getParentClass();

            $class = match ($name) {
                'self' => $declaring_class?->getName(),
                'parent' => $parent !== false ? $parent?->getName() : null,
                default => $name
            };

            return $class !== null && $value instanceof $class;

        }

        return match ($name) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'callable' => is_callable($value),
            'false' => $value === false,
            'float' => is_float($value) || is_int($value),
            'int' => is_int($value),
            'iterable' => is_iterable($value),
            'mixed' => true,
            'null' => $value === null,
            'object' => is_object($value),
            'string' => is_string($value),
            'true' => $value === true,
            default => false
        };

    }

}