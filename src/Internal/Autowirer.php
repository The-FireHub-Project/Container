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

use FireHub\Container\Exception\ServiceInstantiationException;
use Closure, ReflectionClass, ReflectionException, ReflectionNamedType, ReflectionParameter,
    ReflectionIntersectionType, ReflectionUnionType;

/**
 * ### Automatically constructs registered services
 *
 * Resolves constructor dependencies using registered service definitions.
 *
 * Only class and interface dependencies are supported. Scalar, union, and intersection types require explicit
 * factory definitions.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class Autowirer {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param Closure(class-string):object $resolve <p>
     * Context-aware service resolver.
     * </p>
     * @param Closure(class-string):bool $has <p>
     * Explicit service registration checker.
     * </p>
     * @param \FireHub\Container\Internal\TypeMatcher $matcher <p>
     * Dependency type matcher.
     * </p>
     * @param \FireHub\Container\Internal\CandidateSelector $selector <p>
     * Dependency candidate selector.
     * </p>
     * @param \FireHub\Container\Internal\ConstructorMetadataCache $metadata <p>
     * Shared constructor metadata cache.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Closure $resolve,
        private Closure $has,
        private TypeMatcher $matcher,
        private CandidateSelector $selector,
        private ConstructorMetadataCache $metadata
    ) {}

    /**
     * ### Constructs a service with resolved dependencies
     *
     * Inspects the constructor and resolves registered or implicitly constructible object dependencies.
     *
     * Parameters with default values use their defaults when no eligible dependency is available.
     * Nullable parameters without default values remain required.
     * Variadic parameters are left empty unless explicitly supplied through a future binding mechanism.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::resolveParameter() To resolve a constructor parameter.
     * @uses \FireHub\Container\Internal\ConstructorMetadataCache::get() To get constructor metadata.
     *
     * @param class-string $class <p>
     * Concrete service class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service is not registered.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return object Constructed service instance.
     */
    public function construct (string $class):object {

        $metadata = $this->metadata->get($class);

        if (!$metadata->instantiable)
            throw new ServiceInstantiationException;

        if (!$metadata->hasConstructor)
            return $metadata->reflection->newInstance();

        $arguments = [];

        foreach ($metadata->parameters as $parameter) {

            if ($parameter->isVariadic())
                continue;

            $arguments[] = $this->resolveParameter($parameter);

        }

        return $metadata->reflection->newInstanceArgs($arguments);

    }

    /**
     * ### Resolves a constructor parameter
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::resolve() Resolves a dependency.
     * @uses \FireHub\Container\Internal\TypeMatcher::classId() Resolves a parameter class identifier.
     * @uses \FireHub\Container\Internal\TypeMatcher::unionId() Resolves a union dependency identifier.
     * @uses \FireHub\Container\Internal\CandidateSelector::union() Resolves a union dependency.
     * @uses \FireHub\Container\Internal\CandidateSelector::intersection() Resolves an intersection dependency.
     * @uses \FireHub\Container\Internal\TypeMatcher::isConstructible() Checks if a class is implicitly constructible.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return mixed Resolved argument.
     */
    private function resolveParameter (ReflectionParameter $parameter):mixed {

        $type = $parameter->getType();

        $id = match (true) {
            $type instanceof ReflectionNamedType =>
                $this->matcher->classId($parameter, $type),
            $type instanceof ReflectionUnionType =>
                $this->selector->union($parameter, $type),
            $type instanceof ReflectionIntersectionType =>
                $this->selector->intersection($parameter, $type),
            default => null
        };

        if ($id !== null && (
                ($this->has)($id)
                || $this->matcher->isConstructible($id)
            ))
            return ($this->resolve)($id);

        if ($parameter->isDefaultValueAvailable())
            return $parameter->getDefaultValue();

        throw new ServiceInstantiationException(
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName(),
                'position' => $parameter->getPosition(),
                'type' => $type?->__toString(),
                'nullable' => $type?->allowsNull(),
                'optional' => $parameter->isOptional(),
                'has_default' => $parameter->isDefaultValueAvailable()
            ]
        );

    }

}