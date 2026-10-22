<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.0
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Container\Exception\ServiceInstantiationException;
use Closure, ReflectionIntersectionType, ReflectionUnionType;

/**
 * ### Resolves compiled dependency parameters
 *
 * Resolves parameter dependencies using explicit bindings, contextual bindings, tagged services, collection
 * injection, and automatic dependency selection.
 *
 * The resolver operates within an existing dependency resolution context and does not manage service lifetimes
 * directly.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ParameterResolver {

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
     * @param Closure(class-string, \FireHub\Container\Internal\ResolutionContext):list<object> $resolve_all <p>
     * Context-aware service collection resolver.
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
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $bindings <p>
     * Shared parameter binding registry.
     * </p>
     * @param \FireHub\Container\Internal\ContextualCandidateSelector $contextual_selector <p>
     * Contextual dependency candidate selector.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Closure $resolve,
        private Closure $resolve_tagged,
        private Closure $resolve_all,
        private Closure $has,
        private TypeMatcher $matcher,
        private CandidateSelector $selector,
        private ParameterBindingRegistry $bindings,
        private ContextualCandidateSelector $contextual_selector
    ) {}

    /**
     * ### Resolves a compiled dependency parameter
     *
     * Resolves a parameter using explicit values, configured parameter bindings, injection attributes, contextual
     * bindings, and automatic dependency resolution.
     *
     * Explicit values take precedence over all other resolution mechanisms. Constructor parameter bindings can be
     * disabled for method invocation while preserving contextual dependency resolution.
     *
     * Dependency resolution is performed within the supplied context, preserving service lifetimes and circular
     * dependency detection.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check explicit parameter bindings.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::get() To retrieve explicitly bound values.
     * @uses \FireHub\Container\Internal\ContextualCandidateSelector::select() To select contextual dependencies.
     * @uses \FireHub\Container\Internal\ParameterResolver::selectDependency() To select dependency identifiers.
     * @uses \FireHub\Container\Internal\ParameterResolver::resolveDependency() To resolve selected dependencies.
     * @uses \FireHub\Container\Internal\ParameterResolver::unresolvedParameter() To create resolution exceptions.
     *
     * @param null|class-string $consumer <p>
     * Consumer implementation class used for contextual and parameter bindings, or null when no consumer is available.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled dependency parameter plan.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     * @param bool $has_explicit [optional] <p>
     * Whether an explicit argument value has been supplied for the parameter.
     * </p>
     * @param mixed $explicit [optional] <p>
     * Explicit argument value to use when provided.
     * </p>
     * @param bool $use_bindings [optional] <p>
     * Whether configured parameter bindings should be considered during resolution.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter cannot be resolved or the
     * resolved dependency does not satisfy its declared type.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     *
     * @return mixed Resolved parameter value.
     */
    public function resolve (?string $consumer, ParameterPlan $plan, ResolutionContext $context, bool $has_explicit = false, mixed $explicit = null, bool $use_bindings = true):mixed {

        if ($has_explicit) return $explicit;

        $name = $plan->parameter->getName();

        if (
            $use_bindings
            && $consumer !== null
            && $this->bindings->has($consumer, $name)
        ) return $this->bindings->get($consumer, $name);

        if ($plan->tag !== null)
            return ($this->resolve_tagged)($plan->tag, $context);

        if ($plan->collection !== null)
            return ($this->resolve_all)($plan->collection, $context);

        if ($consumer !== null) {

            $id = $this->contextual_selector->select($consumer, $plan);

            if ($id !== null)
                return $this->resolveDependency($id, $plan, $context, true);

        }

        $id = $this->selectDependency($plan);

        if (
            $id !== null
            && (
                ($this->has)($id)
                || $this->matcher->isConstructible($id)
            )
        ) return $this->resolveDependency($id, $plan, $context);

        if ($plan->hasDefault)
            return $plan->default;

        throw $this->unresolvedParameter($plan);

    }

    /**
     * ### Selects an eligible dependency
     *
     * Determines the service identifier required by a compiled dependency parameter.
     *
     * Named dependency types use their precompiled identifiers. Union and intersection dependency types are evaluated
     * dynamically against currently registered service implementations.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::union() To select a union dependency.
     * @uses \FireHub\Container\Internal\CandidateSelector::intersection() To select an intersection dependency.
     *
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled dependency parameter plan.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple dependency candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the dependency type contains unsupported
     * members.
     *
     * @return null|class-string Selected dependency identifier, or null if no eligible dependency exists.
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
     * ### Resolves and validates a selected dependency
     *
     * Resolves the selected service identifier within the active dependency resolution context.
     *
     * Union and intersection dependencies are validated against their complete declared parameter types.
     * Contextually selected named dependencies are additionally validated against their expected contracts.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::matchesType() To validate composite dependency types.
     *
     * @param class-string $id <p>
     * Selected service identifier.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled dependency parameter plan.
     * </p>
     * @param \FireHub\Container\Internal\ResolutionContext $context <p>
     * Active dependency resolution context.
     * </p>
     * @param bool $contextual [optional] <p>
     * Whether the dependency was selected through contextual binding.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the resolved dependency does not satisfy
     * its declared parameter type.
     *
     * @return object Resolved dependency instance.
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
     * Creates an exception containing diagnostic information about a dependency parameter that could not be resolved
     * through explicit bindings, contextual bindings, registered services, implicit construction, or default values.
     *
     * The diagnostic context includes the declaring class, parameter name, position, declared type, nullability,
     * optionality, and default value availability.
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled dependency parameter plan that could not be resolved.
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