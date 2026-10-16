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
 * Static parameter analysis is performed by the dependency plan cache, while service availability and candidate
 * selection are evaluated dynamically within the active resolution context.
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
     *
     * @return void
     */
    public function __construct (
        private Closure $resolve,
        private Closure $has,
        private TypeMatcher $matcher,
        private CandidateSelector $selector,
        private DependencyPlanCache $plans,
        private ParameterBindingRegistry $bindings
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
     * Executes the precompiled parameter plan while evaluating dependency availability and composite candidate
     * selection against the current service registrations.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::union() To select a union dependency.
     * @uses \FireHub\Container\Internal\CandidateSelector::intersection() To select an intersection dependency.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check implicit constructibility.
     * @uses \FireHub\Container\Internal\CandidateSelector::matchesType() To check if a service implementation.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check if a parameter is bound.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::get() To get a bound parameter value.
     * @uses \FireHub\Container\Resolver::resolve() To resolve a service.
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
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return mixed Resolved argument.
     */
    private function resolveParameter (string $class, ParameterPlan $plan, ResolutionContext $context):mixed {

        $parameter = $plan->parameter;

        $name = $parameter->getName();

        if ($this->bindings->has($class, $name))
            return $this->bindings->get($class, $name);

        $id = $plan->id;

        if ($plan->composite instanceof ReflectionUnionType)
            $id = $this->selector->union(
                $plan->parameter,
                $plan->composite
            );

        elseif ($plan->composite instanceof ReflectionIntersectionType)
            $id = $this->selector->intersection(
                $plan->parameter,
                $plan->composite
            );

        if ($id !== null && (
                ($this->has)($id)
                || $this->matcher->isConstructible($id)
            )) {

            $instance = ($this->resolve)($id, $context);

            if (
                $plan->composite !== null
                && !$this->selector->matchesType(
                    $instance::class,
                    $plan->parameter,
                    $plan->composite
                )
            )
                throw new ServiceInstantiationException(
                    context: [
                        'class' => $plan->parameter->getDeclaringClass()?->getName(),
                        'parameter' => $plan->parameter->getName(),
                        'type' => (string)$plan->composite,
                        'instance' => $instance::class
                    ]
                );

            return $instance;

        }

        if ($plan->hasDefault)
            return $plan->default;

        $parameter = $plan->parameter;

        $type = $parameter->getType();

        throw new ServiceInstantiationException(
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