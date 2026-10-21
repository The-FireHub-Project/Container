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

use FireHub\Container\Exception\AmbiguousDependencyException;
use FireHub\Runtime;
use ReflectionIntersectionType, ReflectionNamedType, ReflectionType, ReflectionUnionType;

/**
 * ### Selects contextual dependency implementations
 *
 * Evaluates contextual dependency bindings against constructor parameter types.
 *
 * Supports named, union, intersection, and disjunctive normal form dependency types without modifying standard
 * dependency candidate selection.
 *
 * Contextual bindings are matched against the exact consumer class. Multiple bindings targeting the same service
 * identifier are treated as a single candidate, while distinct candidates are considered ambiguous.
 *
 * Candidate selection does not instantiate services or validate their runtime types.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ContextualCandidateSelector {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ContextualBindingRegistry $bindings <p>
     * Shared contextual binding registry.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private ContextualBindingRegistry $bindings
    ) {}

    /**
     * ### Selects a contextual dependency implementation
     *
     * Collects contextual bindings associated with the declared parameter types and selects a unique implementation.
     *
     * Returns null when no contextual binding exists. Multiple distinct implementations produce an ambiguity
     * exception, regardless of their registration order.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContextualCandidateSelector::collect() To collect contextual candidates.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if multiple candidates are eligible.
     *
     * @param class-string $consumer <p>
     * Concrete consumer implementation class.
     * </p>
     * @param \FireHub\Container\Internal\ParameterPlan $plan <p>
     * Compiled constructor parameter plan.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple distinct contextual
     * implementations are eligible.
     *
     * @return null|class-string Selected contextual implementation identifier.
     */
    public function select (string $consumer, ParameterPlan $plan):?string {

        $type = $plan->parameter->getType();

        if ($type === null)
            return null;

        $candidates = $this->collect($consumer, $type);

        return match (Runtime\Arr\Inspection::count($candidates)) {
            0 => null,
            1 => $candidates[0], // @phpstan-ignore offsetAccess.notFound
            default => throw new AmbiguousDependencyException(
                context: [
                    'class' => $consumer,
                    'parameter' => $plan->parameter->getName(),
                    'type' => (string)$type,
                    'candidates' => $candidates,
                ]
            )
        };

    }

    /**
     * ### Collects contextual dependency candidates
     *
     * Traverses named, union, and intersection types recursively, including nested intersection types within
     * disjunctive normal form declarations.
     *
     * Duplicate implementation identifiers are removed while preserving their first occurrence.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContextualBindingRegistry::find() To find contextual bindings.
     * @uses \FireHub\Runtime\Arr\Access::values() To remove duplicate implementation identifiers.
     *
     * @param class-string $consumer <p>
     * Concrete consumer implementation class.
     * </p>
     * @param ReflectionType $type <p>
     * Declared constructor parameter type.
     * </p>
     *
     * @return list<class-string> Distinct contextual implementation identifiers.
     */
    private function collect (string $consumer, ReflectionType $type):array {

        if ($type instanceof ReflectionNamedType) {

            if ($type->isBuiltin())
                return [];

            $implementation = $this->bindings->find(
                $consumer,
                $type->getName() // @phpstan-ignore argument.type
            );

            return $implementation !== null
                ? [$implementation]
                : [];

        }

        if (
            !$type instanceof ReflectionUnionType
            && !$type instanceof ReflectionIntersectionType
        )
            return [];

        $candidates = [];

        foreach ($type->getTypes() as $member)
            foreach ($this->collect($consumer, $member) as $implementation)
                $candidates[$implementation] = $implementation;

        return Runtime\Arr\Access::values($candidates);

    }

}