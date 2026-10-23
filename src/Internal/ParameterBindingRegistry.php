<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Runtime;

/**
 * ### Stores explicit constructor parameter bindings
 *
 * Maintains parameter values associated with individual implementation classes.
 *
 * Explicit bindings take precedence over automatic dependency resolution and constructor defaults.
 * @since 1.0.0
 *
 * @internal
 */
final class ParameterBindingRegistry {

    /**
     * ### Parameter bindings
     * @since 1.0.0
     *
     * @var array<class-string, array<non-empty-string, mixed>>
     */
    private array $bindings = [];

    /**
     * ### Registers constructor parameter bindings
     *
     * Associates parameter names with explicitly configured values for an implementation class.
     *
     * Existing bindings for the same class are replaced.
     * @since 1.0.0
     *
     * @param class-string $class <p>
     * Implementation class.
     * </p>
     * @param array<non-empty-string, mixed> $parameters <p>
     * Parameter names mapped to explicit values.
     * </p>
     *
     * @return void
     */
    public function register (string $class, array $parameters):void {

        $this->bindings[$class] = $parameters;

    }

    /**
     * ### Checks whether a parameter has an explicit binding
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check for a parameter name.
     *
     * @param class-string $class <p>
     * Implementation class.
     * </p>
     * @param non-empty-string $parameter <p>
     * Constructor parameter name.
     * </p>
     *
     * @return bool True if a binding exists.
     */
    public function has (string $class, string $parameter):bool {

        return isset($this->bindings[$class])
            && Runtime\Arr\Access::keyExists($this->bindings[$class], $parameter);

    }

    /**
     * ### Gets an explicitly bound parameter value
     * @since 1.0.0
     *
     * @param class-string $class <p>
     * Implementation class.
     * </p>
     * @param non-empty-string $parameter <p>
     * Constructor parameter name.
     * </p>
     *
     * @return mixed Explicit parameter value.
     */
    public function get (string $class, string $parameter):mixed {

        return $this->bindings[$class][$parameter]; // @phpstan-ignore-line

    }

    /**
     * ### Gets all registered constructor parameter bindings
     *
     * Returns parameter bindings grouped by their implementation classes without modifying the registry.
     * @since 1.0.0
     *
     * @return array<class-string, array<non-empty-string, mixed>> Registered parameter bindings.
     */
    public function all ():array {

        return $this->bindings;

    }

}