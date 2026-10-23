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

namespace FireHub\Container\Validation;

use FireHub\Container\Registry;
use FireHub\Container\Definition\ {
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    ContextualBindingRegistry, DependencyPlanCache, ParameterBindingRegistry
};
use FireHub\Container\Exception\ServiceInstantiationException;
use FireHub\Runtime;
use ReflectionClass, ReflectionIntersectionType, ReflectionNamedType, ReflectionType, ReflectionUnionType;

/**
 * ### Validates contextual dependency binding registrations
 *
 * Performs static validation of contextual dependency configuration independently of dependency graph traversal.
 *
 * Checks consumer classes, dependency contracts, implementation availability, and statically determinable type
 * compatibility without instantiating services or executing factory callbacks.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ContextualBinding {

    /**
     * ### Constructor dependency plan cache
     * @since 1.0.0
     */
    private DependencyPlanCache $plans;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registered service definitions.
     * </p>
     * @param \FireHub\Container\Internal\ContextualBindingRegistry $bindings <p>
     * Registered contextual dependency bindings.
     * </p>
     * @param \FireHub\Container\Internal\ParameterBindingRegistry $parameters <p>
     * Registered constructor parameter bindings.
     * </p>
     * @param \FireHub\Container\Internal\DependencyPlanCache $plans <p>
     * Compiled dependency plan cache.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private ContextualBindingRegistry $bindings,
        private ParameterBindingRegistry $parameters,
        DependencyPlanCache $plans
    ) {

        $this->plans = $plans;

    }

    /**
     * ### Validates contextual dependency bindings
     *
     * Inspects every contextual registration and reports structurally invalid bindings.
     *
     * Factory implementations are not executed because their return types cannot be verified statically.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContextualBindingRegistry::all() To retrieve contextual bindings.
     * @uses \FireHub\Container\Validation\ContextualBinding::implementationOf() To resolve implementations.
     * @uses \FireHub\Container\Validation\ContextualBinding::isUsed() To check if a dependency is used.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isInterface() To check if the identifier is an interface.
     * @uses \FireHub\Runtime\ObjectModel\Relation::instanceOf() To check if the implementation satisfies the contract.
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a constructor parameter contains invalid
     * injection attributes or unsupported dependency metadata.
     *
     * @return list<\FireHub\Container\Validation\Issue> Detected validation issues.
     */
    public function validate ():array {

        $issues = [];

        foreach ($this->bindings->all() as $consumer => $bindings) {

            if (!Runtime\ObjectModel\Inspection::isClass($consumer)) {

                $issues[] = new Issue(
                    IssueType::INVALID_CONTEXTUAL_BINDING,
                    Severity::ERROR,
                    $consumer,
                    "Contextual binding consumer '$consumer' does not exist."
                );

                continue;

            }

            $reflection = new ReflectionClass($consumer);

            if (!$reflection->isInstantiable()) {

                $issues[] = new Issue(
                    IssueType::INVALID_CONTEXTUAL_BINDING,
                    Severity::ERROR,
                    $consumer,
                    "Contextual binding consumer '$consumer' is not instantiable."
                );

                continue;

            }

            foreach ($bindings as $dependency => $implementation) {

                if (
                    !Runtime\ObjectModel\Inspection::isClass($dependency)
                    && !Runtime\ObjectModel\Inspection::isInterface($dependency)
                ) {

                    $issues[] = new Issue(
                        IssueType::INVALID_CONTEXTUAL_BINDING,
                        Severity::ERROR,
                        $consumer,
                        "Contextual dependency contract '$dependency' does not exist."
                    );

                    continue;

                }

                $resolved = $this->implementationOf($implementation);

                if ($resolved === false) {

                    $issues[] = new Issue(
                        IssueType::INVALID_CONTEXTUAL_BINDING,
                        Severity::ERROR,
                        $consumer,
                        "Contextual implementation '$implementation' cannot be resolved."
                    );

                    continue;

                }

                if (
                    $resolved !== null
                    && !Runtime\ObjectModel\Relation::instanceOf($resolved, $dependency)
                ) {

                    $issues[] = new Issue(
                        IssueType::INVALID_CONTEXTUAL_BINDING,
                        Severity::ERROR,
                        $consumer,
                        "Contextual implementation '$resolved' does not satisfy dependency '$dependency'."
                    );

                    continue;

                }

                try {

                    $used = $this->isUsed($consumer, $dependency);

                } catch (ServiceInstantiationException) {

                    continue;

                }

                if (!$used) {

                    $issues[] = new Issue(
                        IssueType::UNUSED_CONTEXTUAL_BINDING,
                        Severity::WARNING,
                        $consumer,
                        "Contextual dependency '$dependency' is not used by constructor injection in '$consumer'."
                    );

                }

            }

        }

        return $issues;

    }

    /**
     * ### Resolves a statically known contextual implementation
     *
     * Follows registered service aliases and returns their concrete implementation types.
     *
     * Returns null for registered factories because their runtime return types cannot be determined without
     * executing the callback. Returns false for missing, invalid, or circular alias targets.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::find() To retrieve service definitions.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     *
     * @param class-string $id <p>
     * Contextual implementation identifier.
     * </p>
     *
     * @return class-string|false|null Statically known implementation, invalid target, or unverifiable factory.
     */
    private function implementationOf (string $id):string|false|null {

        $visited = [];

        while (true) {

            if (isset($visited[$id])) return false;

            $visited[$id] = true;

            $definition = $this->registry->find($id);

            if ($definition instanceof Alias) {

                if (!$this->registry->has($definition->target)) return false;

                $id = $definition->target;

                continue;

            }

            if ($definition instanceof Cls) return $definition->class;

            if ($definition instanceof Instance) return $definition->instance::class;

            if ($definition instanceof Factory) return null;

            if (!Runtime\ObjectModel\Inspection::isClass($id)) return false;

            $reflection = new ReflectionClass($id);

            return $reflection->isInstantiable()
                ? $id
                : false;

        }

    }

    /**
     * ### Checks whether a contextual binding can participate in constructor injection
     *
     * Inspects compiled constructor parameter metadata and determines whether a contextual dependency contract is
     * referenced by an eligible parameter.
     *
     * Named, union, intersection, and DNF parameter types are inspected recursively.
     *
     * Explicit parameter bindings and collection injection attributes take precedence over contextual resolution.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\DependencyPlanCache::get() To retrieve compiled constructor metadata.
     * @uses \FireHub\Container\Internal\ParameterBindingRegistry::has() To check explicit parameter bindings.
     * @uses \FireHub\Container\Validation\ContextualBindingValidator::containsType() To inspect declared types.
     *
     * @param class-string $consumer <p>
     * Consumer implementation class.
     * </p>
     * @param class-string $dependency <p>
     * Contextual dependency contract.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a constructor parameter contains invalid
     * injection attributes or unsupported dependency metadata.
     *
     * @return bool True if the contextual binding can be selected for at least one constructor parameter.
     */
    private function isUsed (string $consumer, string $dependency):bool {

        $plan = $this->plans->get($consumer);

        foreach ($plan->parameters as $parameter) {

            $name = $parameter->parameter->getName();

            if ($this->parameters->has($consumer, $name))
                continue;

            if ($parameter->tag !== null || $parameter->collection !== null)
                continue;

            $type = $parameter->parameter->getType();

            if (
                $type !== null
                && $this->containsType($type, $dependency)
            ) return true;

        }

        return false;

    }

    /**
     * ### Checks whether a declared parameter type references a dependency contract
     *
     * Traverses named, union, intersection, and DNF types recursively without resolving service instances.
     * @since 1.0.0
     *
     * @param ReflectionType $type <p>
     * Declared constructor parameter type.
     * </p>
     * @param class-string $dependency <p>
     * Required dependency contract.
     * </p>
     *
     * @return bool True if the dependency contract occurs within the declared type.
     */
    private function containsType (ReflectionType $type, string $dependency):bool {

        if ($type instanceof ReflectionNamedType)
            return !$type->isBuiltin() && $type->getName() === $dependency;

        if (
            $type instanceof ReflectionUnionType
            || $type instanceof ReflectionIntersectionType
        ) {

            foreach ($type->getTypes() as $member)
                if ($this->containsType($member, $dependency))
                    return true;

        }

        return false;

    }

}