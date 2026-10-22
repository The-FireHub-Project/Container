<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.0
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Container\Attribute\ {
    InjectAll, Tagged
};
use FireHub\Container\Exception\ServiceInstantiationException;
use FireHub\Runtime;
use ReflectionNamedType, ReflectionParameter, ReflectionUnionType, ReflectionIntersectionType;

/**
 * ### Compiles dependency parameter metadata
 *
 * Analyzes reflected parameters and produces immutable parameter plans used by constructor and method injection.
 *
 * Compiled plans contain statically determined dependency identifiers, composite dependency types, default values,
 * tagged service injection metadata, and collection injection metadata.
 *
 * Service availability, contextual bindings, and dependency candidate selection are evaluated dynamically during
 * parameter resolution rather than compilation.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ParameterPlanCompiler {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Shared dependency type matcher used to resolve named dependency identifiers.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private TypeMatcher $matcher
    ) {}

    /**
     * ### Compiles dependency parameter metadata
     *
     * Extracts the declared parameter type, default value, tagged injection metadata, and collection injection
     * metadata into an immutable parameter plan.
     *
     * Named dependency types are converted into service identifiers. Union and intersection types retain their
     * reflection metadata for dynamic dependency candidate selection.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() To resolve named dependency identifiers.
     * @uses \FireHub\Container\Internal\ParameterPlanCompiler::resolveTag() To resolve tagged injection metadata.
     * @uses \FireHub\Container\Internal\ParameterPlanCompiler::resolveCollection() To resolve collection injection
     * metadata.
     *
     * @param \ReflectionParameter $parameter <p>
     * Reflected constructor or method parameter to compile.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If tagged and collection injection
     * attributes are combined or an injection attribute is invalid.
     *
     * @return \FireHub\Container\Internal\ParameterPlan Compiled dependency parameter plan.
     */
    public function compile (ReflectionParameter $parameter):ParameterPlan {

        $type = $parameter->getType();

        $id = $type instanceof ReflectionNamedType
            ? $this->matcher->classId($parameter, $type) : null;

        $composite = $type instanceof ReflectionUnionType
        || $type instanceof ReflectionIntersectionType
            ? $type : null;

        $has_default = $parameter->isDefaultValueAvailable();

        $tag = $this->resolveTag($parameter);
        $collection = $this->resolveCollection($parameter);

        if ($tag !== null && $collection !== null)
            throw new ServiceInstantiationException(
                'Tagged and collection injection cannot be combined.',
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName(),
                ]
            );

        return new ParameterPlan(
            $parameter,
            $id,
            $composite,
            $has_default,
            $has_default ? $parameter->getDefaultValue() : null,
            $tag,
            $collection
        );

    }

    /**
     * ### Resolves tagged injection metadata
     *
     * Extracts and validates the tagged injection attribute associated with a reflected parameter.
     *
     * Tagged injection requires a non-nullable array parameter and a non-empty tag name. Multiple tagged injection
     * attributes on the same parameter are not supported.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::count() To count declared tagged injection attributes.
     * @uses \FireHub\Runtime\Str\SB\Transform::trim() To validate the tag name.
     *
     * @param \ReflectionParameter $parameter <p>
     * Reflected parameter to inspect for tagged injection metadata.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If multiple tagged injection attributes
     * are declared, the parameter type is unsupported, or the tag name is empty.
     *
     * @return null|non-empty-string Tag name, or null when tagged injection is not declared.
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

    /**
     * ### Resolves collection injection metadata
     *
     * Extracts and validates the collection injection attribute associated with a reflected parameter.
     *
     * Collection injection requires a non-nullable array parameter and an existing class or interface as its element
     * type. Multiple collection injection attributes on the same parameter are not supported.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::count() To count declared collection injection attributes.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check whether the element type is a class.
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isInterface() To check whether the element type is an interface.
     *
     * @param \ReflectionParameter $parameter <p>
     * Reflected parameter to inspect for collection injection metadata.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If multiple collection injection
     * attributes are declared, the parameter type is unsupported, or the requested element type does not exist.
     *
     * @return null|class-string Requested collection element type, or null when collection injection is not declared.
     */
    private function resolveCollection (ReflectionParameter $parameter):?string {

        $attributes = $parameter->getAttributes(InjectAll::class);

        if ($attributes === [])
            return null;

        if (Runtime\Arr\Inspection::count($attributes) > 1)
            throw new ServiceInstantiationException(
                'Multiple collection injection attributes are not supported.',
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
            'Collection injection requires a non-nullable array parameter.',
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
            ]
        );

        /** @var \FireHub\Container\Attribute\InjectAll $attribute */
        $attribute = $attributes[0]->newInstance();

        $collection = $attribute->type;

        if (
            $collection === ''
            || (
                !Runtime\ObjectModel\Inspection::isClass($collection)
                && !Runtime\ObjectModel\Inspection::isInterface($collection)
            )
        ) throw new ServiceInstantiationException(
            'Collection injection requires an existing class or interface.',
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
                'type' => $collection,
            ]
        );

        return $collection;

    }

}