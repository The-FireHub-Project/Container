<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.2
 * @package Container
 */

namespace FireHub\Container\Validation;

use FireHub\Container\Registry;
use FireHub\Container\Definition\ {
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    ConstructorMetadataCache, ContextualBindingRegistry, ContextualCandidateSelector, DependencyPlanCache,
    ParameterBindingRegistry, ParameterPlan, ParameterPlanCompiler, TypeMatcher
};
use FireHub\Container\Exception\AmbiguousDependencyException;
use FireHub\Runtime;

/**
 * ### Validates constructor dependency graphs
 *
 * Performs static analysis of constructor dependencies using the same compiled dependency metadata used by the
 * container's runtime autowiring system.
 *
 * Detects unresolved required dependencies and circular dependency relationships without constructing services,
 * resolving instances, or executing factory callbacks.
 *
 * Explicit parameter bindings are respected and take precedence over automatic dependency resolution.
 *
 * This initial implementation analyzes named dependencies. Composite types, contextual bindings, and collection
 * injection are reserved for later validation stages.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class DependencyGraph {

    /**
     * ### Compiled dependency plan cache
     * @since 1.0.0
     */
    private DependencyPlanCache $plans;

    /**
     * ### Dependency type matcher
     * @since 1.0.0
     */
    private TypeMatcher $matcher;

    /**
     * ### Contextual dependency candidate selector
     *
     * Selects contextual dependency implementations using the same rules as runtime dependency resolution.
     * @since 1.0.0
     */
    private ContextualCandidateSelector $contextual_selector;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registered service definitions.
     * </p>
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $bindings <p>
     * Explicit constructor parameter bindings.
     * </p>
     * @param \FireHub\Container\Internal\ContextualBindingRegistry $contextual <p>
     * Contextual dependency binding registry.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private ParameterBindingRegistry $bindings,
        ContextualBindingRegistry $contextual
    ) {

        $metadata = new ConstructorMetadataCache;

        $this->matcher = new TypeMatcher($metadata);

        $this->plans = new DependencyPlanCache(
            $metadata,
            new ParameterPlanCompiler($this->matcher)
        );

        $this->contextual_selector = new ContextualCandidateSelector(
            $contextual
        );

    }

    /**
     * ### Validates registered constructor dependency graphs
     *
     * Traverses class-based service definitions and their statically resolvable constructor dependencies.
     *
     * Each dependency path is analyzed independently to detect cycles without executing the corresponding
     * constructors.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\DependencyGraph::inspect() To validate dependency paths.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check if a class is constructible.
     * @uses \FireHub\Container\Registry::all() To retrieve all registered service definitions.
     *
     * @return list<\FireHub\Container\Validation\Issue> Detected dependency validation issues.
     */
    public function validate ():array {

        $issues = [];

        foreach ($this->registry->all() as $id => $definition) {

            if (!$definition instanceof Cls)
                continue;

            if (!$this->matcher->isConstructible($definition->class))
                continue;

            $this->inspect(
                $id,
                [],
                $issues
            );

        }

        return $issues;

    }

    /**
     * ### Inspects a service dependency path
     *
     * Recursively examines constructor dependencies while tracking the active service path.
     *
     * A dependency is considered circular when its service identifier already appears in the active path.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::inArray() To check if a service identifier is already in the path.
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Validation\DependencyGraph::inspect() To recursively validate child dependencies.
     * @uses \FireHub\Container\Validation\DependencyGraph::selectContextualDependency() To select contextual dependencies.
     * @uses \FireHub\Container\Validation\DependencyGraph::inspectContextualDependency() To validate contextual dependencies.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check if a class is constructible.
     * @uses \FireHub\Container\Internal\DependencyPlanCache::get() To retrieve a compiled dependency plan.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check if a parameter is explicitly bound.
     * @uses \FireHub\Runtime\Str\SB\Delimiter::implode() To join path segments.
     *
     * @param class-string $id <p>
     * Service identifier being inspected.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return void
     */
    private function inspect (string $id, array $path, array &$issues):void {

        if (Runtime\Arr\Inspection::inArray($path, $id)) {

            $path[] = $id;

            $issues[] = new Issue(
                IssueType::CIRCULAR_DEPENDENCY,
                Severity::ERROR,
                $id,
                'Circular dependency detected: ' . Runtime\Str\SB\Delimiter::implode($path, ' -> '),
                $path
            );

            return;

        }

        $path[] = $id;

        $definition = $this->registry->find($id);

        if ($definition instanceof Alias) {

            $this->inspect(
                $definition->target,
                $path,
                $issues
            );

            return;

        }

        if ($definition instanceof Instance || $definition instanceof Factory)
            return;

        $class = $definition instanceof Cls
            ? $definition->class
            : $id;

        if (!$this->matcher->isConstructible($class))
            return;

        $plan = $this->plans->get($class);

        foreach ($plan->parameters as $parameter) {

            $name = $parameter->parameter->getName();

            if ($this->bindings->has($class, $name))
                continue;

            if ($parameter->tag !== null || $parameter->collection !== null)
                continue;

            [$ambiguous, $contextual] = $this->selectContextualDependency(
                $class,
                $parameter,
                $path,
                $issues
            );

            if ($ambiguous)
                continue;

            if ($contextual !== null) {

                $this->inspectContextualDependency(
                    $class,
                    $contextual,
                    $parameter,
                    $path,
                    $issues
                );

                continue;

            }

            if ($parameter->composite !== null)
                continue;

            $dependency = $parameter->id;

            if ($dependency === null) {

                if (!$parameter->hasDefault) {

                    $issues[] = new Issue(
                        IssueType::MISSING_DEPENDENCY,
                        Severity::ERROR,
                        $id,
                        "Required constructor parameter '$name' of '$class' cannot be automatically resolved.",
                        $path
                    );

                }

                continue;

            }

            if (
                !$this->registry->has($dependency)
                && !$this->matcher->isConstructible($dependency)
            ) {

                if (!$parameter->hasDefault) {

                    $issues[] = new Issue(
                        IssueType::MISSING_DEPENDENCY,
                        Severity::ERROR,
                        $id,
                        "Required dependency '$dependency' of '$class' is not registered or constructible.",
                        [...$path, $dependency]
                    );

                }

                continue;

            }

            $this->inspect(
                $dependency,
                $path,
                $issues
            );

        }

    }

    /**
     * ### Selects a contextual constructor dependency
     *
     * Evaluates contextual bindings for the specified consumer and compiled constructor parameter.
     *
     * Contextual candidate selection follows the same rules as runtime dependency resolution, including support for
     * named, union, intersection, and disjunctive normal form dependency types.
     *
     * Ambiguous contextual bindings are reported as validation issues without interrupting the validation of other
     * services.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContextualCandidateSelector::select() To select contextual dependencies.
     *
     * @param class-string $consumer <p>
     * Concrete consumer implementation class.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $parameter <p>
     * Compiled constructor parameter.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return array{0: bool, 1: null|class-string} Whether contextual selection was ambiguous and the selected
     * dependency identifier, if available.
     */
    private function selectContextualDependency (string $consumer, ParameterPlan $parameter, array $path, array &$issues):array {

        try {

            return [
                false,
                $this->contextual_selector->select(
                    $consumer,
                    $parameter
                )
            ];

        } catch (AmbiguousDependencyException) {

            $name = $parameter->parameter->getName();

            $issues[] = new Issue(
                IssueType::AMBIGUOUS_DEPENDENCY,
                Severity::ERROR,
                $consumer,
                "Multiple contextual implementations are eligible for parameter '$name' of '$consumer'.",
                $path
            );

            return [true, null];

        }

    }

    /**
     * ### Inspects a contextually selected dependency
     *
     * Validates a contextual dependency target and continues dependency graph traversal when its implementation
     * can be determined statically.
     *
     * Registered factory targets cannot be fully inspected without execution.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::has() To check if a service is registered.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check if a class is constructible.
     * @uses \FireHub\Container\Validation\DependencyGraph::inspect() To recursively validate child dependencies.
     * @uses \FireHub\Container\Internal\CandidateSelector::matchesType() To check if a service implementation
     * satisfies a dependency type.
     * @uses \FireHub\Container\Registry::has() To check if a service is registered.
     * @uses \FireHub\Container\Registry::implementations() To retrieve all registered service implementations.
     * @uses \FireHub\Container\Validation\DependencyGraph::inspect() To recursively validate child dependencies.
     * @uses \FireHub\Container\Validation\DependencyGraph::implementationOf() To find the statically known
     * implementation.
     *
     * @param class-string $consumer <p>
     * Consumer implementation class.
     * </p>
     * @param class-string $dependency <p>
     * Selected contextual dependency identifier.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $parameter <p>
     * Compiled constructor parameter.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return void
     */
    private function inspectContextualDependency (string $consumer, string $dependency, ParameterPlan $parameter, array $path, array &$issues):void {

        if (
            !$this->registry->has($dependency)
            && !$this->matcher->isConstructible($dependency)
        ) {

            $issues[] = new Issue(
                IssueType::MISSING_DEPENDENCY,
                Severity::ERROR,
                $consumer,
                "Contextual dependency '$dependency' of '$consumer' is not registered or constructible.",
                [...$path, $dependency]
            );

            return;

        }

        $implementation = $this->implementationOf($dependency);

        if ($implementation !== null) {

            $type = $parameter->parameter->getType();

            if ($type !== null) {

                $metadata = new ConstructorMetadataCache;

                $matcher = new TypeMatcher($metadata);

                $selector = new \FireHub\Container\Internal\CandidateSelector(
                    $matcher,
                    fn (string $id):bool => $this->registry->has($id),
                    fn ():iterable => $this->registry->implementations()
                );

                if (!$selector->matchesType(
                    $implementation,
                    $parameter->parameter,
                    $type
                )) {

                    $issues[] = new Issue(
                        IssueType::INVALID_CONTEXTUAL_BINDING,
                        Severity::ERROR,
                        $consumer,
                        "Contextual implementation '$implementation' does not satisfy parameter '{$parameter->parameter->getName()}' of '$consumer'.",
                        [...$path, $dependency]
                    );

                    return;

                }

            }

        }

        $this->inspect(
            $dependency,
            $path,
            $issues
        );

    }

    /**
     * ### Finds the statically known implementation of a service
     *
     * Follows registered alias chains and returns the concrete implementation class when it can be determined without
     * resolving the service.
     *
     * Factory definitions are excluded because their runtime return types cannot be guaranteed statically.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check if a class is constructible.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return null|class-string Statically known implementation class, or null when unavailable.
     */
    private function implementationOf (string $id):?string {

        $visited = [];

        while (true) {

            if (isset($visited[$id]))
                return null;

            $visited[$id] = true;

            $definition = $this->registry->find($id);

            if ($definition instanceof Alias) {

                $id = $definition->target;

                continue;

            }

            if ($definition instanceof Cls)
                return $definition->class;

            if ($definition instanceof Instance)
                return $definition->instance::class;

            if ($definition instanceof Factory)
                return null;

            return $this->matcher->isConstructible($id)
                ? $id
                : null;

        }

    }

}