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
    Alias, Cls, Factory, Instance, Service
};
use FireHub\Container\Internal\ {
    ContextualResolver, InstanceStore, ResolutionContext, ScopeState
};
use FireHub\Container\Exception\ {
    ScopeRequiredException, ServiceInstantiationException, ServiceTypeMismatchException, UnsupportedDefinitionException
};
use ReflectionClass;

/**
 * ### Resolves registered dependency injection services
 *
 * Creates and retrieves service instances from registered definitions while enforcing their configured lifetimes.
 *
 * Supports singleton, scoped, and transient services, including class-based construction, factory invocation,
 * existing instances, and service aliases.
 *
 * Singleton instances are shared across all resolution contexts, scoped instances are isolated within their
 * associated scope, and transient instances are created for each resolution request.
 *
 * The resolver detects circular dependencies and validates resolved objects against their service identifiers.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Resolver implements ResolverBoundary {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registry containing service definitions.
     * </p>
     * @param \FireHub\Container\Internal\InstanceStore $singletons <p>
     * Shared singleton instance storage.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private InstanceStore $singletons
    ) {}

    /**
     * ### Gets a registered service
     *
     * Resolves the specified service identifier using its registered definition and configured lifetime.
     *
     * Aliases are followed until their underlying service definition is reached.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::resolve() To resolve a service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     *
     * @return TService Resolved service instance.
     */
    public function get (string $id):object {

        return $this->resolve(
            $id,
            new ResolutionContext()
        );

    }

    /**
     * ### Resolves a service within a scope
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::resolve() To resolve a service.
     * @uses \FireHub\Container\Internal\ScopeState::enter() To mark the scope as being currently being resolved.
     * @uses \FireHub\Container\Internal\ScopeState::leave() To remove the scope from the resolution stack.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param \FireHub\Container\Internal\InstanceStore $scoped <p>
     * Scoped instance storage.
     * </p>
     * @param \FireHub\Container\Internal\ScopeState $scope <p>
     * Scope lifecycle state.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return TService Resolved service.
     */
    public function getScoped (string $id, InstanceStore $scoped, ScopeState $scope):object {

        $scope->enter();

        try {

            return $this->resolve(
                $id,
                new ResolutionContext(
                    scoped: $scoped,
                    scope: $scope
                )
            );

        } finally {

            $scope->leave();

        }

    }

    /**
     * ### Checks whether a service is registered
     *
     * Checks whether a service identifier exists without constructing or resolving its instance.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::has() To check for service existence.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return bool True if the service is registered.
     */
    public function has (string $id):bool {

        return $this->registry->has($id);

    }

    /**
     * ### Resolves a service within the specified context
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::get() To retrieve the service definition.
     * @uses \FireHub\Container\Internal\ResolutionStack::enter() To mark the service as being currently being resolved.
     * @uses \FireHub\Container\Internal\ResolutionStack::leave() To remove the service from the resolution stack.
     * @uses \FireHub\Container\Resolver::resolveService() To resolve a service.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the service identifier is not registered.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     *
     * @return TService Resolved service.
     */
    private function resolve (string $id, ResolutionContext $context):object {

        $definition = $this->registry->get($id);

        if ($definition instanceof Alias) {

            $context->stack->enter($id);

            try {

                $instance = $this->resolve($definition->target, $context);

                if (!$instance instanceof $id)
                    throw new ServiceTypeMismatchException(
                        context: [
                            'instance' => $instance,
                        ]
                    );

                return $instance;

            } finally {

                $context->stack->leave();

            }

        }

        if (!$definition instanceof Service)
            throw new UnsupportedDefinitionException;

        return $this->resolveService($id, $definition, $context);

    }

    /**
     * ### Resolves a managed service
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Definition\Service::lifetime() To determine the service lifetime.
     * @uses \FireHub\Container\Internal\ResolutionContext::root() To get the root resolution context.
     * @uses \FireHub\Container\Internal\InstanceStore::set() To store the resolved instance.
     * @uses \FireHub\Container\Internal\InstanceStore::get() To retrieve a previously stored instance.
     * @uses \FireHub\Container\Internal\ResolutionStack::enter() To mark the service as being currently being resolved.
     * @uses \FireHub\Container\Internal\ResolutionStack::leave() To remove the service from the resolution stack.
     * @uses \FireHub\Container\Resolver::create() To construct a service instance.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Service identifier.
     * </p>
     * @param Service $definition <p>
     * Service definition.
     * </p>
     * @param ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service does not match the identifier.
     *
     * @return TService Resolved service.
     */
    private function resolveService (string $id, Service $definition, ResolutionContext $context):object {

        $lifetime = $definition->lifetime();

        if ($lifetime === Lifetime::SINGLETON)
            $context = $context->root();

        $store = match ($lifetime) {
            Lifetime::SINGLETON => $this->singletons,
            Lifetime::SCOPED => $context->scoped
                ?? throw new ScopeRequiredException,
            Lifetime::TRANSIENT => null

        };

        if ($store !== null) {

            $instance = $store->get($id);

            if ($instance !== null)
                /** @var TService $instance */
                return $instance;

        }

        $context->stack->enter($id);

        try {

            $instance = $this->create($definition, $context);

            if (!$instance instanceof $id)
                throw new ServiceTypeMismatchException;

            $store?->set($id, $instance);

            return $instance;

        } finally {

            $context->stack->leave();

        }

    }

    /**
     * ### Creates a context-bound dependency resolver
     *
     * Creates a resolver that preserves the current resolution context across nested factory calls.
     *
     * This ensures that circular dependency detection and lifetime restrictions remain effective throughout
     * the entire dependency resolution chain.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::resolve() To resolve a service.
     * @uses \FireHub\Container\Resolver::has() To check for service existence.
     * @uses \FireHub\Container\Internal\ScopeState::assertOpen() To check if the current scope is open.
     * @uses \FireHub\Container\Internal\ScopeState::enter() To mark the current scope as being currently being resolved.
     * @uses \FireHub\Container\Internal\ScopeState::leave() To remove the current scope from the resolution stack.
     *
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @return \FireHub\Container\Internal\ContextualResolver Context-bound resolver.
     */
    private function contextual (ResolutionContext $context):ContextualResolver {

        return new ContextualResolver(
            function (string $id) use ($context):object {

                $context->scope?->enter();

                try {

                    return $this->resolve($id, $context);

                } finally {

                    $context->scope?->leave();

                }

            },
            function (string $id) use ($context):bool {

                $context->scope?->assertOpen();

                return $this->has($id);

            }
        );

    }

    /**
     * ### Creates a service instance
     *
     * Constructs an object using the supplied service definition.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::contextual() To create a contextual resolver.
     *
     * @param Service $definition <p>
     * Service definition.
     * </p>
     *
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the class is not instantiable.
     *
     * @return object Created service instance.
     */
    private function create (Service $definition, ResolutionContext $context):object {

        return match (true) {

            $definition instanceof Instance => $definition->instance,

            $definition instanceof Factory => ($definition->factory)(
                $this->contextual($context)
            ),

            $definition instanceof Cls => $this->construct($definition->class),

            default => throw new UnsupportedDefinitionException

        };

    }

    /**
     * ### Constructs a class-based service
     *
     * Creates an instance of the specified concrete class.
     *
     * Constructor dependency injection is not yet supported.
     * @since 1.0.0
     *
     * @param class-string $class <p>
     * Concrete implementation class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the class is not instantiable.
     *
     * @return object Created service instance.
     */
    private function construct (string $class):object {

        try {

            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable())
                throw new ServiceInstantiationException(
                    context: [
                        'class' => $class
                    ]
                );

            return $reflection->newInstance();

        } catch (ServiceInstantiationException $exception) {

            throw $exception;

        } catch (\Throwable $exception) {

            throw new ServiceInstantiationException(
                previous: $exception
            );

        }

    }

}