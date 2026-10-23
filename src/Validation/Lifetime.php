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

use FireHub\Core\Meta\Enum\Container\Lifetime as LifetimeService;
use FireHub\Container\Registry;
use FireHub\Container\Definition\ {
    Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    CandidateSelector, CanonicalIdCache, ContextualBindingRegistry, ContextualCandidateSelector, DependencyPlanCache,
    ParameterBindingRegistry, ParameterPlan, TagRegistry, TypeMatcher
};
use FireHub\Container\Exception\ {
    AmbiguousDependencyException, ServiceInstantiationException
};
use FireHub\Runtime;
use ReflectionIntersectionType, ReflectionUnionType;

/**
 * ### Validates service lifetime relationships
 *
 * Performs static analysis of constructor dependency graphs to detect singleton services that capture scoped
 * dependencies.
 *
 * Supports direct and transitive dependency relationships without constructing services or executing factories.
 *
 * Explicit constructor parameter bindings are excluded because their values are supplied independently of
 * automatic dependency resolution.
 *
 * This initial implementation analyzes named constructor dependencies. Composite, contextual, tagged, and
 * collection dependencies are reserved for later validation stages.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Lifetime {

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
     * @since 1.0.0
     */
    private ContextualCandidateSelector $contextual_selector;

    /**
     * ### Composite dependency candidate selector
     * @since 1.0.0
     */
    private CandidateSelector $selector;

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
     * @param \FireHub\Container\Internal\TagRegistry $tags <p>
     * Registered service tags.
     * </p>
     * @param \FireHub\Container\Internal\DependencyPlanCache $plans <p>
     * Compiled dependency plans.
     * </p>
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Dependency type matcher.
     * </p>
     * @param \FireHub\Container\Internal\CanonicalIdCache $canonical <p>
     * Canonical service identifier cache.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private ParameterBindingRegistry $bindings,
        ContextualBindingRegistry $contextual,
        private TagRegistry $tags,
        DependencyPlanCache $plans,
        TypeMatcher $matcher,
        private CanonicalIdCache $canonical
    ) {

        $this->plans = $plans;

        $this->matcher = $matcher;

        $this->contextual_selector = new ContextualCandidateSelector(
            $contextual
        );

        $this->selector = new CandidateSelector(
            $this->matcher,
            fn (string $id):bool => $this->registry->has($id),
            fn ():iterable => $this->registry->implementations()
        );

    }

    /**
     * ### Validates registered service lifetime relationships
     *
     * Traverses singleton dependency graphs and reports scoped dependencies captured directly or transitively.
     *
     * Each singleton is analyzed independently, and repeated dependency paths are deduplicated.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Lifetime::inspect() To validate dependency paths.
     * @uses \FireHub\Container\Registry::all() To retrieve all registered service definitions.
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the composite type is unsupported.
     *
     * @return list<\FireHub\Container\Validation\Issue> Detected lifetime issues.
     */
    public function validate ():array {

        $issues = []; $reported = [];
        foreach ($this->registry->all() as $id => $definition) {

            if (!$definition instanceof Cls) continue;

            if ($definition->lifetime() !== LifetimeService::SINGLETON) continue;

            $this->inspect(
                $id,
                $id,
                [],
                $issues,
                $reported
            );

        }

        return $issues;

    }

    /**
     * ### Inspects a singleton dependency path
     *
     * Traverses statically resolvable constructor dependencies and reports scoped services encountered along
     * the active path.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Lifetime::canonicalId() To resolve a canonical service identifier.
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Definition\Cls::lifetime() To check the service lifetime.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() To check if a class is constructible.
     * @uses \FireHub\Container\Internal\DependencyPlanCache::get() To retrieve a dependency plan.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check if a parameter is explicitly bound.
     * @uses \FireHub\Runtime\Arr\Inspection::inArray() To check if a service identifier is already in the path.
     * @uses \FireHub\Container\Validation\Lifetime::inspectTagged() To recursively validate tagged dependencies.
     * @uses \FireHub\Container\Validation\Lifetime::inspectCollection() To recursively validate collection dependencies.
     * @uses \FireHub\Container\Validation\Lifetime::selectComposite() To select composite dependencies.

     *
     * @param class-string $root <p>
     * Root singleton service identifier.
     * </p>
     * @param class-string $id <p>
     * Current dependency identifier.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     * @param array<string, true> $reported <p>
     * Previously reported lifetime relationships.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the composite type is unsupported.
     *
     * @return void
     */
    private function inspect (string $root, string $id, array $path, array &$issues, array &$reported):void {

        $id = $this->canonicalId($id);

        if ($id === null) return;

        if (Runtime\Arr\Inspection::inArray($path, $id)) return;

        $path[] = $id;

        $definition = $this->registry->find($id);

        if ($definition instanceof Cls) {

            if ($definition->lifetime() === LifetimeService::SCOPED) {

                $key = $root . "\0" . $id;

                if (!isset($reported[$key])) {

                    $reported[$key] = true;

                    $issues[] = new Issue(
                        IssueType::LIFETIME_MISMATCH,
                        Severity::ERROR,
                        $root,
                        "Singleton service '$root' captures scoped dependency '$id'.",
                        $path
                    );

                }

                return;

            }

            $class = $definition->class;

        } elseif ($definition instanceof Instance || $definition instanceof Factory) {

            return;

        } else {

            $class = $id;

        }

        if (!$this->matcher->isConstructible($class))
            return;

        try {

            $plan = $this->plans->get($class);

        } catch (ServiceInstantiationException) {

            return;

        }

        foreach ($plan->parameters as $parameter) {

            $name = $parameter->parameter->getName();

            if ($this->bindings->has($class, $name))
                continue;

            if ($parameter->tag !== null) {

                $this->inspectTagged(
                    $root,
                    $parameter->tag,
                    $path,
                    $issues,
                    $reported
                );

                continue;

            }

            if ($parameter->collection !== null) {

                $this->inspectCollection(
                    $root,
                    $parameter->collection,
                    $path,
                    $issues,
                    $reported
                );

                continue;

            }

            try {

                $dependency = $this->contextual_selector->select(
                    $class,
                    $parameter
                );

            } catch (AmbiguousDependencyException) {

                continue;

            }

            if ($dependency === null) {

                if ($parameter->composite !== null) {

                    try {

                        $dependency = $this->selectComposite($parameter);

                    } catch (AmbiguousDependencyException) {

                        continue;

                    }

                } else {

                    $dependency = $parameter->id;

                }

            }

            if ($dependency === null) continue;

            if (
                !$this->registry->has($dependency)
                && !$this->matcher->isConstructible($dependency)
            ) continue;

            $this->inspect(
                $root,
                $dependency,
                $path,
                $issues,
                $reported
            );

        }

    }

    /**
     * ### Resolves a canonical service identifier
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Canonical::get() To retrieve the canonical identifier.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return null|class-string Canonical service identifier.
     */
    private function canonicalId (string $id):?string {

        return $this->canonical->get($id);

    }

    /**
     * ### Selects a composite dependency
     *
     * Resolves a unique service candidate for union, intersection, and DNF constructor parameter types.
     *
     * Candidate selection follows the same rules as runtime dependency resolution.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::union() To select union and DNF dependencies.
     * @uses \FireHub\Container\Internal\CandidateSelector::intersection() To select intersection dependencies.
     *
     * @param \FireHub\Container\Internal\ParameterPlan $parameter <p>
     * Compiled constructor parameter.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the composite type is unsupported.
     *
     * @return null|class-string Selected dependency identifier.
     */
    private function selectComposite (ParameterPlan $parameter):?string {

        $type = $parameter->composite;

        if ($type === null)
            return null;

        if ($type instanceof ReflectionUnionType)
            return $this->selector->union(
                $parameter->parameter,
                $type
            );

        if ($type instanceof ReflectionIntersectionType) // @phpstan-ignore instanceof.alwaysTrue
            return $this->selector->intersection(
                $parameter->parameter,
                $type
            );

        return null; // @phpstan-ignore deadCode.unreachable

    }

    /**
     * ### Inspects tagged dependency lifetimes
     *
     * Traverses registered services associated with a tag and validates their lifetime relationships.
     *
     * Missing services are ignored because dependency graph validation reports them separately.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TagRegistry::get() To retrieve tagged services.
     * @uses \FireHub\Container\Registry::has() To check if a tagged service exists.
     * @uses \FireHub\Container\Validation\Lifetime::inspect() To inspect dependency lifetimes.
     *
     * @param class-string $root <p>
     * Root singleton service identifier.
     * </p>
     * @param non-empty-string $tag <p>
     * Service tag.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     * @param array<string, true> $reported <p>
     * Previously reported lifetime relationships.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the composite type is unsupported.
     *
     * @return void
     */
    private function inspectTagged (string $root, string $tag, array $path, array &$issues, array &$reported):void {

        foreach ($this->tags->get($tag) as $dependency) {

            if (!$this->registry->has($dependency))
                continue;

            $this->inspect(
                $root,
                $dependency,
                $path,
                $issues,
                $reported
            );

        }

    }

    /**
     * ### Inspects collection dependency lifetimes
     *
     * Traverses all registered services compatible with the requested collection element type.
     *
     * Alias targets are deduplicated, and factory callbacks are never executed.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::all() To retrieve registered services.
     * @uses \FireHub\Container\Validation\Lifetime::canonicalId() To resolve aliases.
     * @uses \FireHub\Container\Validation\Lifetime::matchesCollectionType() To check collection compatibility.
     * @uses \FireHub\Container\Validation\Lifetime::inspect() To inspect dependency lifetimes.
     *
     * @param class-string $root <p>
     * Root singleton service identifier.
     * </p>
     * @param class-string $type <p>
     * Requested collection element type.
     * </p>
     * @param list<class-string> $path <p>
     * Active dependency path.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     * @param array<string, true> $reported <p>
     * Previously reported lifetime relationships.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the composite type is unsupported.
     *
     * @return void
     */
    private function inspectCollection (string $root, string $type, array $path, array &$issues, array &$reported):void {

        /** @var array<class-string, true> $resolved */
        $resolved = [];

        foreach ($this->registry->all() as $id => $definition) {

            $target = $this->canonicalId($id);

            if ($target === null) continue;

            if (isset($resolved[$target])) continue;

            if (!$this->matchesCollectionType($id, $target, $type)) continue;

            $resolved[$target] = true;

            $this->inspect(
                $root,
                $id,
                $path,
                $issues,
                $reported
            );

        }

    }

    /**
     * ### Checks collection candidate compatibility
     *
     * Matches registered service identifiers, canonical identifiers, and known implementation types against a
     * requested collection element contract.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TypeMatcher::matches() To check type compatibility.
     * @uses \FireHub\Container\Registry::find() To retrieve registered definitions.
     *
     * @param class-string $id <p>
     * Original service identifier.
     * </p>
     * @param class-string $target <p>
     * Canonical service identifier.
     * </p>
     * @param class-string $type <p>
     * Requested collection element type.
     * </p>
     *
     * @return bool True if the service matches the collection element type.
     */
    private function matchesCollectionType (string $id, string $target, string $type):bool {

        if ($this->matcher->matches($id, $type))
            return true;

        if ($this->matcher->matches($target, $type))
            return true;

        $definition = $this->registry->find($target);

        return match (true) {
            $definition instanceof Cls => $this->matcher->matches($definition->class, $type),
            $definition instanceof Instance => $definition->instance instanceof $type,
            default => false
        };

    }

}