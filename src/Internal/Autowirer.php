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

use FireHub\Container\Exception\ServiceInstantiationException;
use Closure, ReflectionIntersectionType, ReflectionUnionType;

/**
 * ### Constructs services using compiled dependency plans
 *
 * Executes cached dependency construction plans to instantiate services and resolve their constructor dependencies.
 *
 * Static parameter analysis is performed by the dependency plan cache, while service availability, contextual
 * bindings, and candidate selection are evaluated dynamically within the active resolution context.
 *
 * Explicit parameter bindings take precedence over contextual bindings and automatic dependency resolution.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Autowirer {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param Closure(class-string, \FireHub\Container\Internal\ResolutionContext):object $resolve <p>
     * Context-aware service resolver.
     * </p>
     * @param Closure(non-empty-string, \FireHub\Container\Internal\ResolutionContext):list<object> $resolve_tagged <p>
     * Context-aware tagged service collection resolver.
     * </p>
     * @param Closure(class-string):bool $has <p>
     * Explicit service registration checker.
     * </p>
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Dependency type matcher.
     * </p>
     * @param \FireHub\Container\Internal\CandidateSelector $selector <p>
     * Dependency candidate selector.
     * </p>
     * @param \FireHub\Container\Internal\DependencyPlanCache $plans <p>
     * Shared dependency plan cache.
     * </p>
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $bindings <p>
     * Shared parameter binding registry.
     * </p>
     * @param \FireHub\Container\Internal\ContextualCandidateSelector $contextual_selector <p>
     * Shared contextual dependency selector.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Closure $resolve,
        private Closure $resolve_tagged,
        private Closure $has,
        private TypeMatcher $matcher,
        private CandidateSelector $selector,
        private DependencyPlanCache $plans,
        private ParameterBindingRegistry $bindings,
        private ContextualCandidateSelector $contextual_selector
    ) {}

    /**
     * ### Constructs a service using its compiled dependency plan
     *
     * Resolves constructor dependencies dynamically while reusing previously compiled parameter information.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\DependencyPlanCache::get() To retrieve the compiled construction plan.
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To get constructor metadata.
     *
     * @param class-string $class <p>
     * Concrete service class.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service is not registered.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return object Constructed service instance.
     */
    public function construct (string $class, ResolutionContext $context):object {

        $plan = $this->plans->get($class);

        $metadata = $plan->metadata;

        if (!$metadata->instantiable)
            throw new ServiceInstantiationException;

        if (!$metadata->hasConstructor)
            return $metadata->reflection->newInstance();

        $arguments = [];

        foreach ($plan->parameters as $parameter)
            $arguments[] = $this->resolveParameter(
                $class,
                $parameter,
                $context
            );

        return $metadata->reflection->newInstanceArgs($arguments);

    }

    /**
     * ### Resolves a compiled constructor parameter
     *
     * Resolves constructor arguments according to the following precedence:
     *
     * 1. Explicit parameter binding.
     * 2. Contextual dependency binding.
     * 3. Registered or implicitly constructible dependency.
     * 4. Constructor default value.
     *
     * Contextual bindings apply only to named dependency types and the exact consumer implementation class.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check explicit parameter bindings.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::get() To retrieve explicitly bound values.
     * @uses \FireHub\Container\Internal\ContextualBindingRegistry::find() To find contextual dependency bindings.
     * @uses \FireHub\Container\Internal\Autowirer::selectDependency() To select a dependency identifier.
     * @uses \FireHub\Container\Internal\Autowirer::resolveDependency() To resolve selected dependencies.
     * @uses \FireHub\Container\Internal\Autowirer::unresolvedParameter() To create resolution exceptions.
     *
     * @param class-string $class <p>
     * Concrete service class.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled constructor parameter plan.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the dependency cannot be resolved.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return mixed Resolved argument.
     */
    private function resolveParameter (string $class, ParameterPlan $plan, ResolutionContext $context):mixed {

        $name = $plan->parameter->getName();

        if ($this->bindings->has($class, $name))
            return $this->bindings->get($class, $name);

        if ($plan->tag !== null)
            return ($this->resolve_tagged)($plan->tag, $context);

        $id = $this->contextual_selector->select($class, $plan);

        if ($id !== null)
            return $this->resolveDependency($id, $plan, $context, true);

        $id = $this->selectDependency($plan);

        if ($id !== null && (
                ($this->has)($id)
                || $this->matcher->isConstructible($id)
            )
        ) return $this->resolveDependency($id, $plan, $context);

        if ($plan->hasDefault)
            return $plan->default;

        throw $this->unresolvedParameter($plan);

    }

    /**
     * ### Selects a dependency identifier
     *
     * Determines the service identifier required by a constructor parameter.
     *
     * Named dependency types use their precompiled identifiers. Union and intersection dependency types are
     * evaluated dynamically against the currently registered service implementations.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::union() To select a union dependency.
     * @uses \FireHub\Container\Internal\CandidateSelector::intersection() To select an intersection dependency.
     *
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled constructor parameter plan.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the union contains an unsupported
     * intersection member.
     *
     * @return null|class-string Selected dependency identifier.
     */
    private function selectDependency (ParameterPlan $plan):?string {

        return match (true) {
            $plan->composite instanceof ReflectionUnionType =>
                $this->selector->union(
                    $plan->parameter,
                    $plan->composite
                ),
            $plan->composite instanceof ReflectionIntersectionType =>
                $this->selector->intersection(
                    $plan->parameter,
                    $plan->composite
                ),
            default => $plan->id
        };

    }

    /**
     * ### Resolves a selected dependency
     *
     * Resolves the selected service identifier within the active resolution context.
     *
     * For union and intersection dependency types, validates that the resolved instance satisfies the complete
     * declared parameter type.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Resolver::resolve() To resolve the selected dependency.
     * @uses \FireHub\Container\Internal\CandidateSelector::matchesType() To validate composite dependency types.
     *
     * @param class-string $id <p>
     * Selected dependency identifier.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled constructor parameter plan.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active resolution context.
     * </p>
     * @param bool $contextual [optional] <p>
     * Whether the dependency was selected through contextual binding.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the resolved dependency does not satisfy
     * its declared composite type.
     *
     * @return object Resolved dependency.
     */
    private function resolveDependency (string $id, ParameterPlan $plan, ResolutionContext $context, bool $contextual = false):object {

        $instance = ($this->resolve)($id, $context);

        if (
            $plan->composite !== null
            && !$this->selector->matchesType(
                $instance::class,
                $plan->parameter,
                $plan->composite
            )
        ) throw new ServiceInstantiationException(
                context: [
                    'class' => $plan->parameter->getDeclaringClass()?->getName(),
                    'parameter' => $plan->parameter->getName(),
                    'type' => (string)$plan->composite,
                    'instance' => $instance::class,
                ]
            );

        if (
            $contextual
            && $plan->composite === null
            && $plan->id !== null
        ) {

            $expected = $plan->id;

            if (!$instance instanceof $expected)
                throw new ServiceInstantiationException(
                    context: [
                        'class' => $plan->parameter->getDeclaringClass()?->getName(),
                        'parameter' => $plan->parameter->getName(),
                        'type' => $expected,
                        'implementation' => $id,
                        'instance' => $instance::class,
                    ]
                );

        }

        return $instance;

    }

    /**
     * ### Creates an unresolved parameter exception
     *
     * Creates an exception containing diagnostic information about a constructor parameter that could not be
     * resolved through explicit bindings, contextual bindings, registered services, implicit construction, or
     * default values.
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled constructor parameter plan.
     * </p>
     *
     * @return \FireHub\Container\Exception\ServiceInstantiationException Parameter resolution exception.
     */
    private function unresolvedParameter (ParameterPlan $plan):ServiceInstantiationException {

        $parameter = $plan->parameter;

        $type = $parameter->getType();

        return new ServiceInstantiationException(
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
                'position' => $parameter->getPosition(),
                'type' => $type?->__toString(),
                'nullable' => $type?->allowsNull(),
                'optional' => $parameter->isOptional(),
                'has_default' => $plan->hasDefault
            ]
        );

    }

}