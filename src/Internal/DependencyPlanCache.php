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

use FireHub\Container\Attribute\Tagged;
use FireHub\Container\Exception\ServiceInstantiationException;
use FireHub\Runtime;
use ReflectionNamedType, ReflectionParameter, ReflectionUnionType, ReflectionIntersectionType;

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
     * @uses \FireHub\Container\Internal\DependencyPlanCache::compileParameter() To compile constructor parameter
     * metadata.
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To retrieve constructor metadata.
     *
     * @param class-string $class <p>
     * Service implementation class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If multiple tagged attributes are declared,
     * the parameter type is unsupported, or the tag name is empty.
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

            $parameters[] = $this->compileParameter($parameter);

        }

        return $this->plans[$class] = new DependencyPlan(
            $metadata,
            $parameters
        );

    }

    /**
     * ### Compiles constructor parameter metadata
     *
     * Extracts dependency type information, default values, and tagged service injection metadata.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() To resolve named dependency identifiers.
     * @uses \FireHub\Container\Internal\DependencyPlanCache::resolveTag() To resolve tagged injection metadata.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter reflection.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If multiple tagged attributes are declared,
     * the parameter type is unsupported, or the tag name is empty.
     *
     * @return \FireHub\Container\Internal\ParameterPlan Compiled parameter plan.
     */
    private function compileParameter (ReflectionParameter $parameter):ParameterPlan {

        $type = $parameter->getType();

        $id = $type instanceof ReflectionNamedType
            ? $this->matcher->classId($parameter, $type)
            : null;

        $composite = $type instanceof ReflectionUnionType
        || $type instanceof ReflectionIntersectionType
            ? $type
            : null;

        $has_default = $parameter->isDefaultValueAvailable();

        return new ParameterPlan(
            $parameter,
            $id,
            $composite,
            $has_default,
            $has_default
                ? $parameter->getDefaultValue()
                : null,
            $this->resolveTag($parameter)
        );

    }

    /**
     * ### Resolves tagged injection metadata
     *
     * Extracts and validates the tagged service injection attribute associated with a constructor parameter.
     *
     * Tagged injection requires a non-nullable array parameter and a non-empty tag name.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::count() To count the number of declared tagged attributes.
     * @uses \FireHub\Runtime\Str\SB\Transform::trim() To trim whitespace from tag names.
     *
     * @param \ReflectionParameter $parameter <p>
     * Constructor parameter reflection.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If multiple tagged attributes are declared,
     * the parameter type is unsupported, or the tag name is empty.
     *
     * @return null|non-empty-string Tag name, or null if the parameter is not tagged.
     */
    private function resolveTag (ReflectionParameter $parameter):?string {

        $attributes = $parameter->getAttributes(Tagged::class);

        if ($attributes === [])
            return null;

        if (Runtime\Arr\Inspection::count($attributes) > 1)
            throw new ServiceInstantiationException(
                'Multiple tagged attributes are not supported',
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName(),
                ]
            );

        $type = $parameter->getType();

        if (
            !$type instanceof ReflectionNamedType
            || $type->getName() !== 'array'
            || $type->allowsNull()
        ) throw new ServiceInstantiationException(
            'Tagged injection requires a non-nullable array parameter.',
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
            ]
        );

        /** @var \FireHub\Container\Attribute\Tagged $attribute */
        $attribute = $attributes[0]->newInstance();

        if (Runtime\Str\SB\Transform::trim($attribute->tag) === '')
            throw new ServiceInstantiationException(
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName(),
                    'reason' => 'Tagged injection requires a non-empty tag name.'
                ]
            );

        return $attribute->tag;

    }

}