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

namespace FireHub\Container;

use FireHub\Container\Validation\ {
    ContextualBinding, DependencyGraph, Issue, IssueType, ParameterBinding, Result, Severity
};
use FireHub\Container\Definition\ {
    Alias, Cls, Factory, Instance
};
use FireHub\Container\Internal\ {
    ContextualBindingRegistry, ParameterBindingRegistry, TagRegistry
};
use FireHub\Runtime;
use ReflectionClass, ReflectionException;

/**
 * ### Validates container service definitions
 *
 * Performs static analysis of registered dependency injection service definitions without constructing services,
 * resolving dependencies, or executing factory callbacks.
 *
 * The validator checks class-based definitions, existing instances, and alias chains for structural correctness and
 * type compatibility.
 *
 * Factory definitions are reported as statically unverifiable because their execution behavior cannot be determined
 * without invoking the factory callback.
 *
 * Validation does not modify the service registry or container lifecycle state.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Validator {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registry containing service definitions.
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
     *
     * @return void
     */
    public function __construct (
        private Registry $registry,
        private ParameterBindingRegistry $bindings,
        private ContextualBindingRegistry $contextual,
        private TagRegistry $tags
    ) {}

    /**
     * ### Validates registered service definitions
     *
     * Inspects registered service definitions and collects all independently detectable structural problems.
     *
     * Constructor dependencies and service lifetime relationships are not analyzed at this stage.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\DependencyGraph::validate() To validate dependency graphs.
     * @uses \FireHub\Container\Internal\ParameterBinding::validate() To validate parameter bindings.
     * @uses \FireHub\Container\Registry::all() To retrieve all registered service definitions.
     * @uses \FireHub\Container\Validator::validateClass() To validate class-based definitions.
     * @uses \FireHub\Container\Validator::validateInstance() To validate existing instance definitions.
     * @uses \FireHub\Container\Validator::validateAlias() To validate alias chains.
     * @uses \FireHub\Runtime\Arr\Mutation::push() To append validation issues.
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If a constructor parameter contains invalid
     * injection attributes or unsupported dependency metadata.
     *
     * @return \FireHub\Container\Validation\Result Validation result.
     */
    public function validate ():Result {

        $issues = [];

        foreach ($this->registry->all() as $id => $definition) {

            if ($definition instanceof Cls) {

                $this->validateClass($id, $definition, $issues);

            } elseif ($definition instanceof Instance) {

                $this->validateInstance($id, $definition, $issues);

            } elseif ($definition instanceof Alias) {

                $this->validateAlias($id, $issues);

            } elseif ($definition instanceof Factory) {

                $issues[] = new Issue(
                    IssueType::UNVERIFIABLE_FACTORY,
                    Severity::WARNING,
                    $id,
                    'Factory execution cannot be statically validated.'
                );

            }

        }

        $dependency_issues = new DependencyGraph(
            $this->registry,
            $this->bindings,
            $this->contextual,
            $this->tags
        )->validate();

        Runtime\Arr\Mutation::push($issues, ...$dependency_issues);

        $binding_issues = new ContextualBinding(
            $this->registry,
            $this->contextual,
            $this->bindings
        )->validate();

        Runtime\Arr\Mutation::push($issues, ...$binding_issues);

        /** @var list<\FireHub\Container\Validation\Issue> $issues */
        return new Result($issues);

    }

    /**
     * ### Validates a class-based service definition
     *
     * Verifies that the implementation exists, is instantiable, and is compatible with the registered service
     * identifier when the identifier represents an existing PHP class or interface.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validator::validateTypeCompatibility() To check type compatibility.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     *
     * @param class-string $id <p>
     * Registered service identifier.
     * </p>
     * @param \FireHub\Container\Definition\Cls<object> $definition <p>
     * Class-based service definition.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return void
     */
    private function validateClass (string $id, Cls $definition, array &$issues):void {

        $class = $definition->class;

        if (!Runtime\ObjectModel\Inspection::isClass($class)) {

            $issues[] = new Issue(
                IssueType::MISSING_CLASS,
                Severity::ERROR,
                $id,
                "Implementation class '$class' does not exist."
            );

            return;

        }

        try {

            $reflection = new ReflectionClass($class);

        } catch (ReflectionException) { // @phpstan-ignore catch.neverThrown

            $issues[] = new Issue(
                IssueType::MISSING_CLASS,
                Severity::ERROR,
                $id,
                "Implementation class '$class' cannot be reflected."
            );

            return;

        }

        if (!$reflection->isInstantiable()) {

            $issues[] = new Issue(
                IssueType::NON_INSTANTIABLE_CLASS,
                Severity::ERROR,
                $id,
                "Implementation class '$class' is not instantiable."
            );

        }

        $this->validateTypeCompatibility(
            $id,
            $class,
            $issues
        );

    }

    /**
     * ### Validates an existing service instance
     *
     * Verifies that the registered object is compatible with its service identifier when the identifier
     * represents an existing PHP class or interface.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validator::validateTypeCompatibility() To check type compatibility.
     *
     * @param class-string $id <p>
     * Registered service identifier.
     * </p>
     * @param \FireHub\Container\Definition\Instance $definition <p>
     * Existing instance definition.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return void
     */
    private function validateInstance (string $id, Instance $definition, array &$issues):void {

        $this->validateTypeCompatibility(
            $id,
            $definition->instance::class,
            $issues
        );

    }

    /**
     * ### Validates a service alias
     *
     * Traverses the alias chain to detect missing targets, circular references, and incompatible terminal
     * implementations.
     *
     * Alias traversal does not resolve or instantiate services.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validator::validateTypeCompatibility() To check type compatibility.
     * @uses \FireHub\Container\Registry::find() To retrieve the target service definition.
     * @uses \FireHub\Runtime\Str\SB\Delimiter::implode() To join path segments.
     *
     * @param class-string $id <p>
     * Alias service identifier.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     *
     * @return void
     */
    private function validateAlias (string $id, array &$issues):void {

        $visited = [];
        $path = [];

        $current = $id;

        while (true) {

            if (isset($visited[$current])) {

                $path[] = $current;

                $issues[] = new Issue(
                    IssueType::CIRCULAR_ALIAS,
                    Severity::ERROR,
                    $id,
                    'Circular alias chain detected: ' . Runtime\Str\SB\Delimiter::implode($path, ' -> '),
                    $path
                );

                return;

            }

            $visited[$current] = true;
            $path[] = $current;

            $definition = $this->registry->find($current);

            if ($definition === null) {

                $issues[] = new Issue(
                    IssueType::INVALID_ALIAS,
                    Severity::ERROR,
                    $id,
                    "Alias target '$current' is not registered.",
                    $path
                );

                return;

            }

            if ($definition instanceof Alias) {

                $current = $definition->target;

                continue;

            }

            if ($definition instanceof Cls) {

                $this->validateTypeCompatibility(
                    $id,
                    $definition->class,
                    $issues,
                    $path
                );

            } elseif ($definition instanceof Instance) {

                $this->validateTypeCompatibility(
                    $id,
                    $definition->instance::class,
                    $issues,
                    $path
                );

            }

            return;

        }

    }

    /**
     * ### Validates service type compatibility
     *
     * Checks whether the implementation satisfies the service identifier when the identifier represents an
     * existing class or interface.
     *
     * Identifiers that do not represent loadable PHP types are not checked for type compatibility.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isInterface() To check if the identifier is an interface.
     * @uses \FireHub\Runtime\ObjectModel\Relation::instanceOf() To check if the implementation satisfies the contract.
     *
     * @param class-string $id <p>
     * Registered service identifier.
     * </p>
     * @param class-string $implementation <p>
     * Implementation class.
     * </p>
     * @param list<\FireHub\Container\Validation\Issue> $issues <p>
     * Collected validation issues.
     * </p>
     * @param list<non-empty-string> $path [optional] <p>
     * Dependency or alias path associated with the issue.
     * </p>
     *
     * @return void
     */
    private function validateTypeCompatibility (string $id, string $implementation, array &$issues, array $path = []):void {

        if (
            !Runtime\ObjectModel\Inspection::isClass($id)
            && !Runtime\ObjectModel\Inspection::isInterface($id)
        ) return;

        if (
            Runtime\ObjectModel\Relation::instanceOf($implementation, $id)
        ) return;

        $issues[] = new Issue(
            IssueType::TYPE_MISMATCH,
            Severity::ERROR,
            $id,
            "Implementation '$implementation' is not compatible with service '$id'.",
            $path
        );

    }

}