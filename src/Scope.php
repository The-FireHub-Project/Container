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

use FireHub\Core\Boundary\Container\Scope as ScopeBoundary;
use FireHub\Container\Internal\ {
    DisposalTracker, InstanceStore, ScopeRegistry, ScopeState
};
use Closure;

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
 */
final readonly class Scope implements ScopeBoundary {

    /**
     * ### Scoped instance store
     * @since 1.0.0
     */
    private InstanceStore $instances;

    /**
     * ### Scope lifecycle state
     * @since 1.0.0
     */
    private ScopeState $state;

    /**
     * ### Scoped disposable resource tracker
     * @since 1.0.0
     */
    private DisposalTracker $disposables;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Resolver $resolver <p>
     * Parent container's service resolver.
     * </p>
     * @param \FireHub\Container\Internal\ScopeRegistry $registry <p>
     * Parent container's scope registry.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Resolver $resolver,
        private ScopeRegistry $registry
    ) {

        $this->instances = new InstanceStore;

        $this->state = new ScopeState;

        $this->disposables = new DisposalTracker;

    }

    /**
     * ### Resolves tagged services within the current scope
     *
     * Resolves all services associated with the specified tag using the current scope.
     *
     * Scoped services are reused within this scope, singleton services are shared with the parent container, and
     * transient services are constructed on each resolution.
     *
     * Resolution is prohibited after the scope has been closed.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(AuthenticationMiddleware::class);
     * $container->scoped(AuthorizationMiddleware::class);
     * $container->transient(LoggingMiddleware::class);
     *
     * $container->tag(
     *     'http.middleware',
     *     AuthenticationMiddleware::class,
     *     AuthorizationMiddleware::class,
     *     LoggingMiddleware::class
     * );
     *
     * $scope = $container->createScope();
     *
     * $first = $scope->tagged('http.middleware');
     * $second = $scope->tagged('http.middleware');
     *
     * // Singleton and scoped instances are reused.
     * $first[0] === $second[0]; // true
     * $first[1] === $second[1]; // true
     *
     * // Transient instances are created on each resolution.
     * $first[2] !== $second[2]; // true
     *
     * $scope->close();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::taggedScoped() To resolve tagged services within the scope.
     *
     * @param non-empty-string $tag <p>
     * Service tag name.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
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

        return $this->resolver->taggedScoped(
            $tag,
            $this->instances,
            $this->state,
            $this->disposables
        );

    }

    /**
     * ### Invokes a callable within the current scope
     *
     * Executes the specified callable by combining explicitly supplied arguments with dependencies resolved from the
     * current scope.
     *
     * Singleton services are shared with the parent container, scoped services are reused within this scope, and
     * transient services are constructed for each resolution.
     *
     * The scope remains open after invocation and may be reused until explicitly closed.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->scoped(DatabaseConnection::class);
     *
     * $scope = $container->createScope();
     *
     * try {
     *
     *     $service = new UserService;
     *
     *     $result = $scope->invoke(
     *         [$service, 'create'],
     *         ['username' => 'Danijel']
     *     );
     *
     * } finally {
     *
     *     $scope->close();
     *
     * }
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::invokeScoped() To execute the callable within the current scope.
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
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return mixed Value returned by the invoked callable.
     */
    public function invoke (callable $callback, array $arguments = []):mixed {

        return $this->resolver->invokeScoped(
            $callback,
            $arguments,
            $this->instances,
            $this->state,
            $this->disposables
        );

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
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->scoped(DatabaseConnection::class);
     *
     * $scope = $container->createScope();
     *
     * $first = $scope->get(DatabaseConnection::class);
     * $second = $scope->get(DatabaseConnection::class);
     *
     * // Scoped services are reused within the same scope.
     * $first === $second; // true
     *
     * $scope->close();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::getScoped() To resolve a scoped service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return TService Resolved service.
     */
    public function get (string $id):object {

        return $this->resolver->getScoped(
            $id,
            $this->instances,
            $this->state,
            $this->disposables
        );

    }

    /**
     * ### Checks whether a service is registered
     *
     * Determines whether the parent container contains a definition for the specified service identifier.
     *
     * The operation does not instantiate the service.
     *
     * Registration checks are prohibited after the scope has been closed.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->singleton(Logger::class);
     *
     * $scope = $container->createScope();
     *
     * $scope->has(Logger::class); // true
     * $scope->has(Cache::class);  // false
     *
     * $scope->close();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::assertOpen() To check if the scope is open.
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

        $this->state->assertOpen();

        return $this->resolver->has($id);

    }

    /**
     * ### Resolves all matching services within the current scope
     *
     * Resolves all explicitly registered services compatible with the specified class or interface.
     *
     * Scoped services are reused within the current scope, singleton services are shared with the parent container,
     * and transient services are created for each resolution.
     *
     * Resolution is prohibited after the scope has been closed.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->scoped(FileLogger::class);
     * $container->singleton(ConsoleLogger::class);
     *
     * $scope = $container->createScope();
     *
     * $loggers = $scope->all(LoggerInterface::class);
     *
     * $scope->close();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::allScoped() To resolve matching services within the scope.
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
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return list<TService> Matching service instances.
     */
    public function all (string $type):array {

        return $this->resolver->allScoped(
            $type,
            $this->instances,
            $this->state,
            $this->disposables
        );

    }

    /**
     * ### Executes a callback within the scope
     *
     * Executes the provided callback using this scope as its dependency resolver.
     *
     * The scope is automatically closed after callback execution, including when the callback throws an exception.
     *
     * Nested execution of the same scope is not permitted.
     *
     * <code>
     * use FireHub\Container\Container;
     * use FireHub\Core\Boundary\Resolver;
     *
     * $container = new Container;
     *
     * $container->scoped(DatabaseConnection::class);
     *
     * $scope = $container->createScope();
     *
     * $scope->run(
     *     function (Resolver $resolver):void {
     *
     *         $connection = $resolver->get(DatabaseConnection::class);
     *
     *         $connection->connect();
     *
     *     }
     * );
     *
     * // The scope is automatically closed after execution.
     * $scope->isClosed(); // true
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::beginRun() To begin scope execution.
     * @uses \FireHub\Container\Internal\ScopeState::endRun() To end scope execution.
     * @uses \FireHub\Container\Scope::close() To close the scope.
     *
     * @template TResult
     *
     * @param Closure(\FireHub\Core\Boundary\Resolver):TResult $callback <p>
     * Callback to execute within the scope.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     * @throws \FireHub\Container\Exception\ScopeActiveException If service resolution is active.
     * @throws \FireHub\Container\Exception\ServiceDisposalException If an instance fails to release its resources.
     *
     * @return TResult Callback result.
     */
    public function run (Closure $callback):mixed {

        $this->state->beginRun();

        try {

            return $callback($this);

        } finally {

            try {

                $this->state->endRun();

            } finally {

                $this->close();

            }

        }

    }

    /**
     * ### Checks whether the scope is closed
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $scope = $container->createScope();
     *
     * $scope->isClosed(); // false
     *
     * $scope->close();
     *
     * $scope->isClosed(); // true
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::isClosed() To check the scope lifecycle state.
     *
     * @return bool True if the scope is closed, false otherwise.
     */
    public function isClosed ():bool {

        return $this->state->isClosed();

    }

    /**
     * ### Closes the scope
     *
     * Prevents further service resolution and disposes of all owned scoped service instances.
     *
     * Disposable services are released in reverse registration order.
     *
     * The instance store is cleared even when resource disposal fails.
     *
     * Closing an already closed scope has no additional effect.
     *
     * Singleton instances and resources owned by other scopes remain unaffected.
     *
     * <code>
     * use FireHub\Container\Container;
     *
     * $container = new Container;
     *
     * $container->scoped(DatabaseConnection::class);
     *
     * $scope = $container->createScope();
     *
     * $connection = $scope->get(DatabaseConnection::class);
     *
     * $scope->close();
     *
     * $scope->isClosed(); // true
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::close() To close the scope.
     * @uses \FireHub\Container\Internal\InstanceStore::clear() To clear the instance store.
     * @uses \FireHub\Container\Internal\DisposalTracker::dispose() To dispose of disposable resources.
     * @uses \FireHub\Container\Internal\ScopeRegistry::unregister() To unregister the scope from the registry.
     *
     * @throws \FireHub\Container\Exception\ScopeActiveException If service resolution is active.
     * @throws \FireHub\Container\Exception\ServiceDisposalException If an instance fails to release its resources.
     *
     * @return void
     */
    public function close ():void {

        $this->state->close();

        try {

            $this->disposables->dispose();

        } finally {

            try {

                $this->instances->clear();

            } finally {

                $this->registry->unregister($this);

            }

        }

    }

}