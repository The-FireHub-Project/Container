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
use FireHub\Core\Meta\Enum\Container\Lifetime;
use FireHub\Container\Definition\ {
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    InstanceStore, ParameterBindingRegistry
};
use FireHub\Container\Exception\ContainerFrozenException;
use Closure;

/**
 * ### Dependency injection container
 *
 * Provides the central entry point for registering and resolving services within the FireHub dependency injection
 * system.
 *
 * The container coordinates service definitions through a registry and delegates service resolution to a dedicated
 * resolver.
 *
 * Service definitions may be registered during the configuration phase. Once the container is frozen, further
 * registrations are prohibited.
 *
 * The container supports singleton, scoped, and transient service lifetimes. Scoped services require an active
 * resolution scope.
 * @since 1.0.0
 */
final class Container implements ResolverBoundary {

    /**
     * ### Service registry
     * @since 1.0.0
     */
    private readonly Registry $registry;

    /**
     * ### Service resolver
     * @since 1.0.0
     */
    private readonly Resolver $resolver;

    /**
     * ### Constructor parameter binding registry
     * @since 1.0.0
     */
    private readonly ParameterBindingRegistry $parameters;

    /**
     * ### Container freeze state
     * @var bool
     */
    private bool $frozen = false;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @return void
     */
    public function __construct () {

        $this->registry = new Registry;
        $this->parameters = new ParameterBindingRegistry;

        $this->resolver = new Resolver(
            $this->registry,
            new InstanceStore,
            $this->parameters
        );

    }

    /**
     * ### Creates a dependency injection scope
     *
     * Creates an independent resolution scope associated with this container.
     *
     * Each scope maintains its own cache of scoped service instances while sharing singleton instances and service
     * definitions with the parent container.
     *
     * Scopes must be explicitly closed when they are no longer needed.
     * @since 1.0.0
     *
     * @return \FireHub\Container\Scope Created resolution scope.
     */
    public function createScope ():Scope {

        return new Scope($this->resolver);

    }

    /**
     * ### Registers a service definition
     *
     * Associates a service identifier with its definition.
     *
     * Registration is only permitted while the container is not frozen.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::assertMutable() To check if the container is frozen.
     * @uses \FireHub\Container\Registry::register() To register a service definition.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param \FireHub\Container\Definition $definition <p>
     * Service definition.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function register (string $id, Definition $definition):self {

        $this->assertMutable();

        $this->registry->register($id, $definition);

        return $this;

    }

    /**
     * ### Registers a class-based service
     *
     * Associates a service identifier with a concrete implementation class.
     *
     * The service is instantiated lazily according to the specified lifetime.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param class-string<TService> $class <p>
     * Concrete implementation class.
     * </p>
     * @param \FireHub\Core\Meta\Enum\Container\Lifetime $lifetime [optional] <p>
     * Service lifetime.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function bind (string $id, string $class, Lifetime $lifetime = Lifetime::TRANSIENT):self {

        return $this->register(
            $id,
            new Cls($class, $lifetime)
        );

    }

    /**
     * ### Registers a singleton service
     *
     * Registers a class-based service instantiated once per container.
     *
     * When no implementation is provided, the identifier itself is used as the implementation class.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::bind() To register a class-based service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param null|class-string<TService> $class [optional] <p>
     * Implementation class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function singleton (string $id, ?string $class = null):self {

        return $this->bind(
            $id,
            $class ?? $id,
            Lifetime::SINGLETON
        );

    }

    /**
     * ### Registers a scoped service
     *
     * Registers a class-based service instantiated once per resolution scope.
     *
     * Each independent scope receives its own service instance.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::bind() To register a class-based service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param null|class-string<TService> $class [optional] <p>
     * Implementation class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function scoped (string $id, ?string $class = null):self {

        return $this->bind(
            $id,
            $class ?? $id,
            Lifetime::SCOPED
        );

    }

    /**
     * ### Registers a transient service
     *
     * Registers a class-based service instantiated on each resolution.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::bind() To register a class-based service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param null|class-string<TService> $class [optional] <p>
     * Implementation class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function transient (string $id, ?string $class = null):self {

        return $this->bind(
            $id,
            $class ?? $id
        );

    }

    /**
     * ### Registers a factory-based service
     *
     * Registers a factory responsible for creating the requested service.
     *
     * The factory receives a contextual resolver to resolve dependencies within the current resolution context.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param Closure(\FireHub\Core\Boundary\Resolver):TService $factory <p>
     * Service factory.
     * </p>
     * @param \FireHub\Core\Meta\Enum\Container\Lifetime $lifetime [optional] <p>
     * Service lifetime.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function factory (string $id, Closure $factory, Lifetime $lifetime = Lifetime::TRANSIENT):self {

        return $this->register(
            $id,
            new Factory($factory, $lifetime)
        );

    }

    /**
     * ### Registers an existing service instance
     *
     * Registers an already constructed object as a singleton service.
     *
     * The provided instance is returned directly during resolution.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param TService $instance <p>
     * Existing service instance.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function instance (string $id, object $instance):self {

        return $this->register(
            $id,
            new Instance($instance)
        );

    }

    /**
     * ### Registers a service alias
     *
     * Associates an additional identifier with another registered service.
     *
     * Aliases preserve the lifetime and identity of the target service.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Alias identifier.
     * </p>
     * @param class-string<TService> $target <p>
     * Target service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     *
     * @return $this Container instance.
     */
    public function alias (string $id, string $target):self {

        return $this->register(
            $id,
            new Alias($target)
        );

    }

    /**
     * ### Registers explicit constructor parameter bindings
     *
     * Associates an implementation class with explicitly configured constructor argument values.
     *
     * Explicit bindings take precedence over automatic dependency resolution and constructor default values.
     *
     * Bindings are associated with the implementation class rather than a service identifier, allowing them to work
     * with both registered and implicitly constructed services.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::assertMutable() To ensure the container accepts configuration changes.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::register() To register parameter bindings.
     *
     * @param class-string $class <p>
     * Implementation class.
     * </p>
     * @param array<non-empty-string, mixed> $parameters <p>
     * Constructor parameter names mapped to explicit values.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     *
     * @return $this Container instance.
     */
    public function bindParameters (string $class, array $parameters):self {

        $this->assertMutable();

        $this->parameters->register($class, $parameters);

        return $this;

    }

    /**
     * ### Resolves a registered service
     *
     * Resolves the requested service using the configured service definitions.
     *
     * Singleton services are shared across the container. Transient services are constructed on each resolution.
     *
     * Scoped services cannot be resolved without an active scope.
     * @since 1.0.0
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible
     * with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return TService Resolved service.
     */
    public function get (string $id):object {

        return $this->resolver->get($id);

    }

    /**
     * ### Checks whether a service is registered
     *
     * Determines whether the registry contains a definition for the specified service identifier.
     *
     * This operation does not instantiate the service.
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return bool True if registered, false otherwise.
     */
    public function has (string $id):bool {

        return $this->registry->has($id);

    }

    /**
     * ### Freezes the container
     *
     * Prevents any further service registrations.
     *
     * Existing service definitions remain available for resolution.
     *
     * Calling this method multiple times has no additional effect.
     * @since 1.0.0
     *
     * @return void
     */
    public function freeze ():void {

        $this->frozen = true;

    }

    /**
     * ### Checks whether the container is frozen
     * @since 1.0.0
     *
     * @return bool True if frozen, false otherwise.
     */
    public function isFrozen ():bool {

        return $this->frozen;

    }

    /**
     * ### Ensures the container accepts registrations
     *
     * Prevents modification of service definitions after the container has been frozen.
     * @since 1.0.0
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     *
     * @return void
     */
    private function assertMutable ():void {

        if ($this->frozen)
            throw new ContainerFrozenException;

    }

}