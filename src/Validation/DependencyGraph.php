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
    ConstructorMetadataCache, DependencyPlanCache, ParameterBindingRegistry,
    ParameterPlanCompiler, TypeMatcher
};
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

}