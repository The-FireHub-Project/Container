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

namespace FireHub\Container;

use FireHub\Core\Boundary\Resolver as ResolverBoundary;
use FireHub\Container\Internal\InstanceStore;
use FireHub\Container\Exception\ScopeClosedException;

/**
 * ### Dependency injection resolution scope
 *
 * Represents an isolated lifetime boundary for scoped service instances.
 *
 * Each scope maintains its own instance store while sharing the parent container's service resolver and singleton
 * instances.
 *
 * Scoped services are created once per scope and reused throughout its lifetime. Singleton services remain shared
 * across all scopes belonging to the same container, while transient services are created on each resolution.
 *
 * A scope can be explicitly closed to release its cached service instances. Once closed, the scope cannot be used
 * for further service resolution.
 *
 * Scopes do not support nested scope creation.
 * @since 1.0.0
 *
 * @internal
 */
final class Scope implements ResolverBoundary {

    /**
     * ### Scoped instance store
     * @since 1.0.0
     */
    private readonly InstanceStore $instances;

    /**
     * ### Scope closure state
     * @since 1.0.0
     */
    private bool $closed = false;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Resolver $resolver <p>
     * Parent container's service resolver.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private readonly Resolver $resolver
    ) {

        $this->instances = new InstanceStore;

    }

    /**
     * ### Resolves a registered service
     *
     * Resolves the requested service within the current scope.
     *
     * Scoped services are cached within this scope, singleton services are shared with the parent container, and
     * transient services are created on each resolution.
     *
     * Resolution is prohibited after the scope has been closed.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Scope::assertOpen() To check if the scope is open.
     * @uses \FireHub\Container\Resolver::getScoped() To resolve a scoped service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope has been closed.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     *
     * @return TService Resolved service.
     */
    public function get (string $id):object {

        $this->assertOpen();

        return $this->resolver->getScoped($id, $this->instances);

    }

    /**
     * ### Checks whether a service is registered
     *
     * Determines whether the parent container contains a definition for the specified service identifier.
     *
     * The operation does not instantiate the service.
     *
     * Registration checks are prohibited after the scope has been closed.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Scope::assertOpen() To check if the scope is open.
     * @uses \FireHub\Container\Resolver::has() To check if a service is registered.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope has been closed.
     *
     * @return bool True if the service is registered, false otherwise.
     */
    public function has (string $id):bool {

        $this->assertOpen();

        return $this->resolver->has($id);

    }

    /**
     * ### Closes the scope
     *
     * Releases all cached scoped service instances and prevents further service resolution through this scope.
     *
     * Closing an already closed scope has no additional effect.
     *
     * Closing a scope does not affect singleton instances maintained by the parent container or instances stored in
     * other scopes.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\InstanceStore::clear() To clear the instance store.
     *
     * @return void
     */
    public function close ():void {

        if ($this->closed)
            return;

        $this->closed = true;

        $this->instances->clear();

    }

    /**
     * ### Checks whether the scope is closed
     * @since 1.0.0
     *
     * @return bool True if the scope is closed, false otherwise.
     */
    public function isClosed ():bool {

        return $this->closed;

    }

    /**
     * ### Ensures the scope is open
     *
     * Prevents service resolution and registration checks after the
     * scope has been closed.
     * @since 1.0.0
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return void
     */
    private function assertOpen ():void {

        if ($this->closed)
            throw new ScopeClosedException;

    }

}