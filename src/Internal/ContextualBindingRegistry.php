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

use FireHub\Container\Exception\DuplicateContextualBindingException;

/**
 * ### Stores contextual dependency bindings
 *
 * Associates consumer implementation classes with specific dependency contracts and their target service identifiers.
 *
 * Contextual bindings do not replace global definitions and are evaluated dynamically during constructor autowiring.
 * @since 1.0.0
 *
 * @internal
 */
final class ContextualBindingRegistry {

    /**
     * ### Contextual dependency bindings
     * @since 1.0.0
     *
     * @var array<class-string, array<class-string, class-string>>
     */
    private array $bindings = [];

    /**
     * ### Registers a contextual dependency binding
     *
     * Associates a consumer class and required dependency with a target service identifier.
     *
     * Duplicate registrations for the same consumer and dependency are rejected.
     * @since 1.0.0
     *
     * @param class-string $consumer <p>
     * Consumer implementation class.
     * </p>
     * @param class-string $dependency <p>
     * Required dependency contract.
     * </p>
     * @param class-string $implementation <p>
     * Target service identifier.
     * </p>
     *
     * @throws DuplicateContextualBindingException If the contextual binding already exists.
     *
     * @return void
     */
    public function register (string $consumer, string $dependency, string $implementation):void {

        if (isset($this->bindings[$consumer][$dependency]))
            throw new DuplicateContextualBindingException;

        $this->bindings[$consumer][$dependency] = $implementation;

    }

    /**
     * ### Finds a contextual dependency binding
     *
     * Returns the target service identifier associated with the specified consumer and dependency.
     *
     * Returns null when no contextual binding exists.
     * @since 1.0.0
     *
     * @param class-string $consumer <p>
     * Consumer implementation class.
     * </p>
     * @param class-string $dependency <p>
     * Required dependency contract.
     * </p>
     *
     * @return null|class-string Target service identifier, or null if not found.
     */
    public function find (string $consumer, string $dependency):?string {

        return $this->bindings[$consumer][$dependency] ?? null;

    }

    /**
     * ### Checks whether a contextual binding exists
     *
     * Checks whether the specified consumer has an explicitly registered dependency override.
     * @since 1.0.0
     *
     * @param class-string $consumer <p>
     * Consumer implementation class.
     * </p>
     * @param class-string $dependency <p>
     * Required dependency contract.
     * </p>
     *
     * @return bool True if the binding exists, false otherwise.
     */
    public function has (string $consumer, string $dependency):bool {

        return isset($this->bindings[$consumer][$dependency]);

    }

    /**
     * ### Gets all registered contextual dependency bindings
     *
     * Returns contextual bindings grouped by their consumer implementation classes.
     *
     * The returned collection preserves the registration order and does not modify the registry.
     * @since 1.0.0
     *
     * @return array<class-string, array<class-string, class-string>> Registered contextual dependency bindings.
     */
    public function all ():array {

        return $this->bindings;

    }

}