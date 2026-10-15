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
use ReflectionClass, ReflectionNamedType, ReflectionParameter;

/**
 * ### Provides dependency type inspection and matching
 *
 * Resolves declared constructor parameter types into service identifiers and evaluates compatibility between
 * concrete implementations and required dependency contracts.
 *
 * Type inspection does not instantiate services or modify registrations.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class TypeMatcher {

    /**
     * ### Resolves a parameter class identifier
     *
     * Converts a named constructor parameter type into a service identifier.
     *
     * Relative class types are resolved against the class declaring the constructor. Built-in types are excluded.
     * @since 1.0.0
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionNamedType $type <p>
     * Declared parameter type.
     * </p>
     *
     * @return null|class-string Resolved service identifier, or null for built-in types.
     */
    public function classId (ReflectionParameter $parameter, ReflectionNamedType $type):?string {

        if ($type->isBuiltin())
            return null;

        $declaringClass = $parameter->getDeclaringClass();

        $parent = $declaringClass?->getParentClass();

        /** @var null|class-string */
        return match ($type->getName()) {
            'self' => $declaringClass?->getName(),
            'parent' => $parent !== false ? $parent?->getName() : null,
            default => $type->getName()
        };

    }

    /**
     * ### Checks whether a class supports implicit construction
     *
     * Determines whether the identifier represents an instantiable concrete class.
     *
     * Constructor dependencies are not resolved during this check.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     *
     * @param class-string $class <p>
     * Class identifier.
     * </p>
     *
     * @return bool True if the class can be implicitly constructed.
     */
    public function isConstructible (string $class):bool {

        if (!Runtime\ObjectModel\Inspection::isClass($class))
            return false;

        return new ReflectionClass($class)->isInstantiable();

    }

    /**
     * ### Checks whether an implementation satisfies a contract
     *
     * Determines whether a concrete implementation is compatible with the specified class or interface.
     *
     * The implementation is not instantiated.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\ObjectModel\Relation::instanceOf() To check if the implementation satisfies the contract.
     *
     * @param class-string $implementation <p>
     * Concrete implementation class.
     * </p>
     * @param class-string $contract <p>
     * Required dependency contract.
     * </p>
     *
     * @return bool True if the implementation satisfies the contract.
     */
    public function matches (string $implementation, string $contract):bool {

        return Runtime\ObjectModel\Relation::instanceOf($implementation, $contract);

    }

}