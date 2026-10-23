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
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    ConstructorMetadataCache, DependencyPlanCache, ParameterBindingRegistry, ParameterPlanCompiler, TypeMatcher
};
use FireHub\Container\Exception\ServiceInstantiationException;
use FireHub\Runtime;

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
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registered service definitions.
     * </p>
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $bindings <p>
     * Explicit constructor parameter bindings.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private ParameterBindingRegistry $bindings
    ) {

        $metadata = new ConstructorMetadataCache;

        $this->matcher = new TypeMatcher($metadata);

        $this->plans = new DependencyPlanCache(
            $metadata,
            new ParameterPlanCompiler($this->matcher)
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

            if ($parameter->tag !== null || $parameter->collection !== null)
                continue;

            if ($parameter->composite !== null)
                continue;

            $dependency = $parameter->id;

            if ($dependency === null)
                continue;

            if (
                !$this->registry->has($dependency)
                && !$this->matcher->isConstructible($dependency)
            )
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
     * ### Resolves the canonical service identifier
     *
     * Follows registered alias chains without resolving service instances.
     *
     * Invalid or circular aliases are ignored because they are reported by the existing service definition
     * validator.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Registry::has() To check if an alias target exists.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return null|class-string Canonical service identifier.
     */
    private function canonicalId (string $id):?string {

        $visited = [];

        while (true) {

            if (isset($visited[$id])) return null;

            $visited[$id] = true;

            $definition = $this->registry->find($id);

            if (!$definition instanceof Alias)
                return $id;

            $id = $definition->target;

            if (!$this->registry->has($id))
                return null;

        }

    }

}