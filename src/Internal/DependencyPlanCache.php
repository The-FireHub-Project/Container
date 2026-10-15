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

use ReflectionNamedType, ReflectionUnionType, ReflectionIntersectionType;

/**
 * ### Compiles and caches dependency construction plans
 *
 * Analyzes constructor parameters once per implementation class and stores the resulting dependency plans.
 *
 * Compiled plans contain only static constructor information. Registered service availability, union and
 * intersection candidate selection, and dependency instantiation remain dynamic.
 * @since 1.0.0
 *
 * @internal
 */
final class DependencyPlanCache {

    /**
     * ### Cached dependency plans
     * @since 1.0.0
     *
     * @var array<class-string, \FireHub\Container\Internal\DependencyPlan>
     */
    private array $plans = [];

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ConstructorMetadataCache $metadata <p>
     * Shared constructor metadata cache.
     * </p>
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Dependency type matcher.
     * </p>
     *
     * @return void
     */
    public function __construct(
        private readonly ConstructorMetadataCache $metadata,
        private readonly TypeMatcher $matcher
    ) {}

    /**
     * ### Gets a compiled dependency plan
     *
     * Returns the cached dependency plan or compiles one from constructor reflection metadata.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() To resolve named dependency identifiers.
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To retrieve constructor metadata.
     *
     * @param class-string $class <p>
     * Service implementation class.
     * </p>
     *
     * @return \FireHub\Container\Internal\DependencyPlan Compiled dependency plan.
     */
    public function get (string $class):DependencyPlan {

        if (isset($this->plans[$class]))
            return $this->plans[$class];

        $metadata = $this->metadata->get($class);

        $parameters = [];

        foreach ($metadata->parameters as $parameter) {

            if ($parameter->isVariadic())
                continue;

            $type = $parameter->getType();

            $id = $type instanceof ReflectionNamedType
                ? $this->matcher->classId($parameter, $type)
                : null;

            $composite = $type instanceof ReflectionUnionType
            || $type instanceof ReflectionIntersectionType
                ? $type
                : null;

            $hasDefault = $parameter->isDefaultValueAvailable();

            $parameters[] = new ParameterPlan(
                $parameter,
                $id,
                $composite,
                $hasDefault,
                $hasDefault
                    ? $parameter->getDefaultValue()
                    : null
            );

        }

        return $this->plans[$class] = new DependencyPlan(
            $metadata,
            $parameters
        );

    }

}