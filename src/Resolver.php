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
    Autowirer, CandidateSelector, ConstructorMetadataCache, ContextualBindingRegistry, ContextualCandidateSelector,
    ContextualResolver, DependencyPlanCache, InstanceStore, ParameterBindingRegistry, ResolutionContext, ScopeState,
    TypeMatcher
};
use FireHub\Container\Exception\ {
    ScopeRequiredException, ServiceNotFoundException, ServiceTypeMismatchException, UnsupportedDefinitionException
};
use FireHub\Runtime;

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
     * ### Constructor metadata cache
     * @since 1.0.0
     */
    private ConstructorMetadataCache $metadata;

    /**
     * ### Reusable dependency autowirer
     * @since 1.0.0
     */
    private Autowirer $autowirer;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::has() To check for service existence.
     * @uses \FireHub\Container\Resolver::get() To retrieve a service definition.
     * @uses \FireHub\Container\Registry::implementations() To get all registered implementations.
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registry containing service definitions.
     * </p>
     * @param \FireHub\Container\Internal\InstanceStore $singletons <p>
     * Shared singleton instance storage.
     * </p>
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $parameters <p>
     * Parameter binding registry.
     * </p>
     * @param \FireHub\Container\Internal\ContextualBindingRegistry $contextual <p>
     * Contextual binding registry.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private InstanceStore $singletons,
        ParameterBindingRegistry $parameters,
        ContextualBindingRegistry $contextual
    ) {

        $this->metadata = new ConstructorMetadataCache;

        $matcher = new TypeMatcher(
            $this->metadata
        );

        $selector = new CandidateSelector(
            $matcher,
            fn (string $id):bool => $this->has($id),
            fn ():iterable => $this->registry->implementations()
        );

        $plans = new DependencyPlanCache(
            $this->metadata,
            $matcher
        );

        $this->autowirer = new Autowirer(
            fn (string $id, ResolutionContext $context):object => $this->resolve($id, $context),
            fn (string $id):bool => $this->has($id),
            $matcher,
            $selector,
            $plans,
            $parameters,
            new ContextualCandidateSelector($contextual)
        );

    }

    /**
     * ### Gets a registered service
     *
     * Resolves the specified service identifier using its registered definition and configured lifetime.
     *
     * Aliases are followed until their underlying service definition is reached.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::resolve() To resolve a service.
     * @uses \FireHub\Container\Internal\InstanceStore::get() To retrieve a previously resolved instance.
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
     * @return TService Resolved service instance.
     */
    public function get (string $id):object {

        $instance = $this->singletons->get($id);

        if ($instance !== null)
            /** @var TService $instance */
            return $instance;

        return $this->resolve(
            $id,
            new ResolutionContext
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
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
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
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Internal\ResolutionStack::enter() To mark the service as being currently being resolved.
     * @uses \FireHub\Container\Internal\ResolutionStack::leave() To remove the service from the resolution stack.
     * @uses \FireHub\Container\Resolver::resolveService() To resolve a service.
     * @uses \FireHub\Container\Resolver::resolveImplicit() To resolve an implicitly constructed service.
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
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the resolved service is not compatible
     * with its registered identifier.
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ScopeRequiredException If the service requires a scope but none is provided.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return TService Resolved service.
     */
    private function resolve (string $id, ResolutionContext $context):object {

        $definition = $this->registry->find($id);

        if ($definition === null)
            return $this->resolveImplicit($id, $context);

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
     * ### Resolves an implicitly constructed service
     *
     * Automatically constructs an unregistered concrete class using constructor dependency injection.
     *
     * Implicit services are never cached or registered and always use transient lifetime semantics.
     *
     * Abstract classes and interfaces cannot be implicitly constructed.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ResolutionStack::enter() To mark the service as being currently being resolved.
     * @uses \FireHub\Container\Internal\ResolutionStack::leave() To remove the service from the resolution stack.
     * @uses \FireHub\Container\Resolver::autowire() To construct an instance using autowiring.
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To retrieve constructor metadata.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     *
     * @template TService of object
     *
     * @param class-string<TService> $id <p>
     * Unregistered concrete class identifier.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the identifier is not an instantiable class.
     * @throws \FireHub\Container\Exception\CircularDependencyException If a circular dependency is detected.
     * @throws \FireHub\Container\Exception\ServiceTypeMismatchException If the constructed instance does not match
     * the identifier.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return TService Constructed service.
     */
    private function resolveImplicit (string $id, ResolutionContext $context):object {

        if (!Runtime\ObjectModel\Inspection::isClass($id))
            throw new ServiceNotFoundException;

        if (!$this->metadata->get($id)->instantiable)
            throw new ServiceNotFoundException;

        $context->stack->enter($id);

        try {

            $instance = $this->autowire($id, $context);

            if (!$instance instanceof $id)
                throw new ServiceTypeMismatchException(
                    context: [
                        'instance' => $instance
                    ]
                );

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
     * @uses \FireHub\Container\Resolver::autowire() To construct an instance using autowiring.
     * @uses \FireHub\Container\Resolver::contextual() To create a contextual resolver.
     *
     * @param Service $definition <p>
     * Service definition.
     * </p>
     *
     * @throws \FireHub\Container\Exception\UnsupportedDefinitionException If the service definition is not supported.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service is not registered.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return object Created service instance.
     */
    private function create (Service $definition, ResolutionContext $context):object {

        return match (true) {
            $definition instanceof Instance => $definition->instance,
            $definition instanceof Factory => ($definition->factory)(
                $this->contextual($context)
            ),
            $definition instanceof Cls => $this->autowire(
                $definition->class,
                $context
            ),
            default => throw new UnsupportedDefinitionException
        };

    }

    /**
     * ### Constructs a service using constructor autowiring
     *
     * Automatically resolves registered constructor dependencies while preserving the current resolution context.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::construct() To construct an instance using autowiring.
     *
     * @param class-string $class <p>
     * Concrete implementation class.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service is not registered.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return object Constructed service instance.
     */
    private function autowire (string $class, ResolutionContext $context):object {

        return $this->autowirer->construct($class, $context);

    }

}