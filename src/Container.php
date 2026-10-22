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

use FireHub\Core\Boundary\ {
    Container\Registrar, Container\Scope as ScopeBoundary, Container as ContainerBoundary
};
use FireHub\Core\Meta\Enum\Container\Lifetime;
use FireHub\Container\Definition\ {
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    ContextualBindingRegistry, InstanceStore, ParameterBindingRegistry, ResolutionContext, TagRegistry
};
use FireHub\Container\Exception\ {
    ContainerFrozenException, InvalidTagNameException, ServiceNotFoundException
};
use FireHub\Runtime;
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
final class Container implements ContainerBoundary, Registrar {

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
     * ### Contextual dependency binding registry
     * @since 1.0.0
     */
    private readonly ContextualBindingRegistry $contextual;

    /**
     * ### Service tag registry
     * @since 1.0.0
     */
    private readonly TagRegistry $tags;

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
        $this->contextual = new ContextualBindingRegistry;
        $this->tags = new TagRegistry;

        $this->resolver = new Resolver(
            $this->registry,
            new InstanceStore,
            $this->parameters,
            $this->contextual,
            $this->tags
        );

    }

    /**
     * {@inheritDoc}
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $scope = $container->createScope();
     * </code>
     *
     * @since 1.0.0
     */
    public function createScope ():ScopeBoundary {

        return new Scope($this->resolver);

    }

    /**
     * ### Registers a service definition
     *
     * Associates a service identifier with its definition.
     *
     * Registration is only permitted while the container is not frozen.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->register(Test::class, new Cls(Test::class));
     * </code>
     *
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
     * ### Registers a contextual dependency binding
     *
     * Associates a specific consumer class with an implementation to be used when resolving the specified dependency.
     *
     * Contextual bindings apply only to the exact consumer class and do not affect global service registrations.
     *
     * The target service retains its configured lifetime.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->bindContextual(
     *     FrontendService::class,
     *     LoggerInterface::class,
     *     FileLogger::class
     * );
     *
     * $frontend = $container->get(FrontendService::class);
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::assertMutable()
     * @uses \FireHub\Container\Internal\ContextualBindingRegistry::register()
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
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateContextualBindingException If the binding already exists.
     *
     * @return $this Container instance.
     */
    public function bindContextual (string $consumer, string $dependency, string $implementation):self {

        $this->assertMutable();

        $this->contextual->register(
            $consumer,
            $dependency,
            $implementation
        );

        return $this;

    }

    /**
     * {@inheritDoc}
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->bind(Test::class, Test::class);
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Test::class);
     *
     * $first = $container->get(Test::class);
     * $second = $container->get(Test::class);
     *
     * // $first === $second
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->scoped(Test::class);
     *
     * $firstScope = $container->createScope();
     * $secondScope = $container->createScope();
     *
     * $first = $firstScope->get(Test::class);
     * $second = $secondScope->get(Test::class);
     *
     * // $first !== $second
     *
     * $firstScope->close();
     * $secondScope->close();
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->transient(Test::class);
     *
     * $first = $container->get(Test::class);
     * $second = $container->get(Test::class);
     *
     * // $first !== $second
         *
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
     * {@inheritDoc}
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->factory(
     *     Test::class,
     *     static fn ($resolver):Test => new Test
     * );
     *
     * $service = $container->get(Test::class);
     * </code>
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Logger::class);
     *
     * $container->factory(
     *     Test::class,
     *     static fn ($resolver):Test => new Test(
     *         $resolver->get(Logger::class)
     *     )
     * );
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     */
    public function factory (string $id, Closure $factory, Lifetime $lifetime = Lifetime::TRANSIENT):self {

        return $this->register(
            $id,
            new Factory($factory, $lifetime)
        );

    }

    /**
     * {@inheritDoc}
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $instance = new Test;
     *
     * $container->instance(Test::class, $instance);
     *
     * $service = $container->get(Test::class);
     *
     * // $service === $instance
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
     */
    public function instance (string $id, object $instance):self {

        return $this->register(
            $id,
            new Instance($instance)
        );

    }

    /**
     * {@inheritDoc}
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Test::class);
     * $container->alias(TestInterface::class, Test::class);
     *
     * $service = $container->get(TestInterface::class);
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::register() To register a service definition.
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the identifier is already registered.
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->bindParameters(
     *     Test::class,
     *     [
     *         'name' => 'FireHub',
     *         'enabled' => true
     *     ]
     * );
     *
     * $service = $container->get(Test::class);
     * </code>
     *
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
     * ### Resolves all registered services matching a type
     *
     * Resolves all explicitly registered services compatible with the specified class or interface.
     *
     * Matching services are returned in registration order while preserving their configured lifetimes.
     *
     * Scoped services require an active scope and cannot be resolved directly through the container.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(FileLogger::class);
     * $container->singleton(ConsoleLogger::class);
     *
     * $loggers = $container->all(LoggerInterface::class);
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::all() To resolve matching services.
     *
     * @template TService of object
     *
     * @param class-string<TService> $type <p>
     * Required service type.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible
     * with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return list<TService> Matching service instances.
     */
    public function all (string $type):array {

        return $this->resolver->all(
            $type,
            new ResolutionContext
        );

    }

    /**
     * ### Associates registered services with a tag
     *
     * Groups explicitly registered service identifiers under the specified tag.
     *
     * Only services registered in the container can be tagged. Implicitly constructible classes are not considered
     * registered services.
     *
     * Service identifiers are stored in registration order, and duplicate identifiers within the same tag are
     * ignored.
     *
     * Registration is atomic: all service identifiers are validated before any tag association is created.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->singleton(AuthorizationMiddleware::class);
     * $container->singleton(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class,
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     * </code>
     *
     * You can also tag services with multiple calls to the `tag()` method:
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->singleton(AuthorizationMiddleware::class);
     * $container->singleton(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class
     * );
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Container::assertMutable() To ensure the container is not frozen.
     * @uses \FireHub\Container\Container::has() To verify explicit service registrations.
     * @uses \FireHub\Container\Internal\TagRegistry::register() To register service tag associations.
     * @uses \FireHub\Runtime\Str\SB\Transform::trim() To validate tag names.
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     * @param class-string ...$services <p>
     * Explicitly registered service identifiers.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If a service is not explicitly registered.
     * @throws \FireHub\Container\Exception\InvalidTagNameException If the tag name is empty.
     *
     * @return $this Container instance.
     */
    public function tag (string $tag, string ...$services):self {

        $this->assertMutable();

        if (Runtime\Str\SB\Transform::trim($tag) === '')
            throw new InvalidTagNameException;

        foreach ($services as $service)
            if (!$this->has($service))
                throw new ServiceNotFoundException;

        $this->tags->register($tag, ...$services);

        return $this;

    }

    /**
     * ### Checks whether a service tag exists
     *
     * Determines whether the specified tag contains at least one registered service identifier.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->singleton(AuthorizationMiddleware::class);
     * $container->singleton(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class,
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     *
     * $container->hasTag('http.middleware');
     *
     * // true
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TagRegistry::has() To check tag registration.
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     *
     * @return bool True if the tag contains registered services, otherwise false.
     */
    public function hasTag (string $tag):bool {

        return $this->tags->has($tag);

    }

    /**
     * ### Retrieves service identifiers associated with a tag
     *
     * Returns explicitly registered service identifiers in their original registration order without resolving or
     * instantiating services.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->singleton(AuthorizationMiddleware::class);
     * $container->singleton(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class,
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     *
     * $container->taggedIds('http.middleware');
     *
     * // [
     * //     AuthenticationMiddleware::class,
     * //     AuthorizationMiddleware::class,
     * //     LoggingMiddleware::class
     * // ]
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TagRegistry::get() To retrieve tagged service identifiers.
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     *
     * @return list<class-string> Tagged service identifiers.
     */
    public function taggedIds (string $tag):array {

        return $this->tags->get($tag);

    }

    /**
     * ### Resolves all services associated with a tag
     *
     * Returns tagged service instances in their registration order.
     *
     * Each service retains its configured lifetime. Scoped services require an active scope.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->singleton(AuthorizationMiddleware::class);
     * $container->singleton(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class,
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     *
     * $container->tagged('http.middleware');
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::tagged() To resolve tagged services.
     *
     * @param non-empty-string $tag <p>
     * Service tag name.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If a tagged service is no longer registered.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If a scoped service is resolved without a scope.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible
     * with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return list<object> Resolved service instances.
     */
    public function tagged (string $tag):array {

        return $this->resolver->tagged(
            $tag,
            new ResolutionContext
        );

    }

    /**
     * ### Invokes a callable using dependency injection
     *
     * Executes the specified callable by combining explicitly supplied arguments with dependencies automatically
     * resolved from the container.
     *
     * The callable may be a closure, named function, instance method, static method, or invokable object.
     *
     * Scoped dependencies require invocation through an active resolution scope.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(LoggerInterface::class, FileLogger::class);
     *
     * $service = new UserService;
     *
     * $result = $container->invoke(
     *     [$service, 'create'],
     *     ['username' => 'Danijel']
     * );
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::invoke() To execute the callable using dependency injection.
     *
     * @param callable $callback <p>
     * Callable to execute.
     * </p>
     * @param array<array-key, mixed> $arguments [optional] <p>
     * Explicit positional and named arguments.
     * </p>
     *
     * @throws \FireHub\Container\Exception\InvalidMethodArgumentException If explicit arguments are invalid.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter cannot be resolved or the
     * resolved dependency does not satisfy its declared type.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     *
     * @return mixed Value returned by the invoked callable.
     */
    public function invoke (callable $callback, array $arguments = []):mixed {

        return $this->resolver->invoke(
            $callback,
            $arguments
        );

    }

    /**
     * ### Resolves a registered service
     *
     * Resolves the requested service using the configured service definitions.
     *
     * Singleton services are shared across the container. Transient services are constructed on each resolution.
     *
     * Scoped services cannot be resolved without an active scope.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Test::class);
     *
     * $service = $container->get(Test::class);
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Test::class);
     *
     * $registered = $container->has(Test::class);
     *
     * // true
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Test::class);
     *
     * $container->freeze();
     *
     * // Existing services remain available.
     * $service = $container->get(Test::class);
     *
     * // Further registrations are prohibited.
     * // $container->singleton(AnotherTest::class);
     * </code>
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function freeze ():void {

        $this->frozen = true;

    }

    /**
     * ### Checks whether the container is frozen
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->isFrozen(); // false
     *
     * $container->freeze();
     *
     * $container->isFrozen(); // true
     * </code>
     *
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