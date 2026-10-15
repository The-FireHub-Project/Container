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

namespace FireHub\Container\Internal;

use FireHub\Container\Exception\ {
    AmbiguousDependencyException, ServiceInstantiationException
};
use FireHub\Runtime;
use Closure, ReflectionIntersectionType, ReflectionNamedType,
    ReflectionParameter, ReflectionUnionType;

/**
 * ### Selects dependency injection candidates
 *
 * Evaluates available service candidates for union and intersection constructor dependency types.
 *
 * Explicit registrations take precedence over implicitly constructible classes when resolving union dependencies.
 *
 * Intersection dependencies are matched against statically known registered implementation types.
 *
 * Candidate selection does not instantiate services.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class CandidateSelector {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Dependency type matcher-
     * </p>
     * @param Closure(class-string):bool $has <p>
     * Explicit service registration checker.
     * </p>
     * @param Closure():(iterable<class-string, class-string>) $candidates <p>
     * Registered service identifiers mapped to implementation classes.
     * </p>
     */
    public function __construct (
        private TypeMatcher $matcher,
        private Closure $has,
        private Closure $candidates
    ) {}

    /**
     * ### Selects a union dependency
     *
     * Selects exactly one eligible service from a union type.
     *
     * Explicitly registered candidates take precedence over implicitly constructible candidates.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::ambiguous() To report multiple candidates.
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() Resolves a parameter class identifier.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() Checks if a class is implicitly constructible.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the union contains multiple candidates.
     * @uses \FireHub\Runtime\Arr\Access::keys() To get the union member identifiers.
     * @uses \FireHub\Runtime\Arr\Access::firstKey() To get the first union member identifier.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionUnionType $type <p>
     * Union dependency type.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the union contains an unsupported
     * intersection member.
     *
     * @return null|class-string Selected service identifier.
     */
    public function union (ReflectionParameter $parameter, ReflectionUnionType $type):?string {

        /** @var array<class-string, true> $registered */
        $registered = [];

        /** @var array<class-string, true> $implicit */
        $implicit = [];

        foreach ($type->getTypes() as $member) {

            if (!$member instanceof ReflectionNamedType)
                throw new ServiceInstantiationException(
                    context: [
                        'class' => $parameter->getDeclaringClass()?->getName(),
                        'parameter' => $parameter->getName(),
                        'type' => (string)$type
                    ]
                );

            $id = $this->matcher->classId($parameter, $member);

            if ($id === null)
                continue;

            if (($this->has)($id)) {

                $registered[$id] = true;

                continue;

            }

            if ($this->matcher->isConstructible($id))
                $implicit[$id] = true;

        }

        $selected = $registered !== [] ? $registered : $implicit;

        if (Runtime\Arr\Inspection::count($selected) > 1)
            $this->ambiguous($parameter, $type, Runtime\Arr\Access::keys($selected));

        return Runtime\Arr\Access::firstKey($selected);

    }

    /**
     * ### Selects an intersection dependency
     *
     * Selects a registered service whose implementation satisfies every contract declared by the intersection type.
     *
     * Factories without statically known implementation types are excluded from candidate discovery.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\CandidateSelector::ambiguous() To report multiple candidates.
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() Resolves a parameter class identifier.
     * @uses \FireHub\Container\Internal\TypeMatcher::matches() To check if a service implementation satisfies a
     * contract.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the union contains multiple candidates.
     * @uses \FireHub\Runtime\Arr\Access::keys() To get the union member identifiers.
     * @uses \FireHub\Runtime\Arr\Access::firstKey() To get the first union member identifier.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionIntersectionType $type <p>
     * Intersection dependency type.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates satisfy the dependency.
     *
     * @return null|class-string Selected service identifier.
     */
    public function intersection (ReflectionParameter $parameter, ReflectionIntersectionType $type):?string {

        /** @var array<class-string, true> $matches */
        $matches = [];

        foreach (($this->candidates)() as $id => $implementation) {

            $matchesAll = true;

            foreach ($type->getTypes() as $member) {

                if (!$member instanceof ReflectionNamedType) {

                    $matchesAll = false;

                    break;

                }

                $contract = $this->matcher->classId($parameter, $member);

                if (
                    $contract === null
                    || !$this->matcher->matches($implementation, $contract)
                ) {

                    $matchesAll = false;

                    break;

                }

            }

            if ($matchesAll)
                $matches[$id] = true;

        }

        if (Runtime\Arr\Inspection::count($matches) > 1)
            $this->ambiguous($parameter, $type, Runtime\Arr\Access::keys($matches));

        return Runtime\Arr\Access::firstKey($matches);

    }

    /**
     * ### Throws an ambiguous dependency exception
     *
     * Reports multiple eligible candidates for a constructor parameter.
     * @since 1.0.0
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionUnionType|ReflectionIntersectionType $type <p>
     * Requested dependency type.
     * </p>
     * @param list<class-string> $candidates <p>
     * Matching service identifiers.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException Always.
     *
     * @return never
     */
    private function ambiguous (ReflectionParameter $parameter, ReflectionUnionType|ReflectionIntersectionType $type, array $candidates):never {

        throw new AmbiguousDependencyException(
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
                'type' => (string)$type,
                'candidates' => $candidates
            ]
        );

    }

}