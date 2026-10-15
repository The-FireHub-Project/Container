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

use FireHub\Container\Exception\ {
    AmbiguousDependencyException, ServiceInstantiationException
};
use FireHub\Runtime;
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
     * Service registration checker.
     * </p>
     * @param Closure():(iterable<class-string, class-string>) $candidates <p>
     * Service candidates provider.
     * </p>
     */
    public function __construct (
        private Closure $resolve,
        private Closure $has,
        private Closure $candidates
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

        try {

            $reflection = new ReflectionClass($class);

        } catch (ReflectionException $exception) { // @phpstan-ignore catch.neverThrown

            throw new ServiceInstantiationException(
                previous: $exception
            );

        }

        if (!$reflection->isInstantiable())
            throw new ServiceInstantiationException(
                context: ['class' => $class]
            );

        $constructor = $reflection->getConstructor();

        if ($constructor === null)
            return $reflection->newInstance();

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {

            if ($parameter->isVariadic())
                continue;

            $arguments[] = $this->resolveParameter($parameter);

        }

        return $reflection->newInstanceArgs($arguments);

    }

    /**
     * ### Checks whether a dependency can be resolved
     *
     * Determines whether the dependency has an explicit registration or represents an implicitly constructible
     * concrete class.
     *
     * This check does not instantiate the dependency.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\ObjectModel\Inspection::isClass() To check if the identifier is a class.
     *
     * @param class-string $id <p>
     * Dependency identifier.
     * </p>
     *
     * @return bool True if resolution can be attempted.
     */
    private function canResolve (string $id):bool {

        if (($this->has)($id))
            return true;

        if (!Runtime\ObjectModel\Inspection::isClass($id))
            return false;

        return new ReflectionClass($id)->isInstantiable();

    }

    /**
     * ### Resolves a parameter class identifier
     *
     * Resolves the declared class or interface type of the constructor parameter.
     *
     * Relative class types are resolved against the class declaring the constructor. Built-in types are not
     * considered service identifiers.
     * @since 1.0.0
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionNamedType $type <p>
     * Declared parameter type.
     * </p>
     *
     * @return null|class-string Resolved service identifier, or null for built-in types.
     */
    private function classId (ReflectionParameter $parameter, ReflectionNamedType $type):?string {

        if ($type->isBuiltin())
            return null;

        /** @var null|class-string */
        return match ($type->getName()) {
            'self' => $parameter->getDeclaringClass()?->getName(),
            'parent' => $parameter->getDeclaringClass()?->getParentClass()?->getName(), // @phpstan-ignore method.nonObject
            default => $type->getName()
        };

    }

    /**
     * ### Resolves a union dependency identifier
     *
     * Selects a unique dependency from a union of class or interface types.
     *
     * Explicitly registered services take precedence over implicitly constructible concrete classes.
     *
     * Multiple candidates at the same priority level are considered ambiguous.
     *
     * This method does not instantiate any dependencies.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the union contains multiple candidates.
     * @uses \FireHub\Runtime\Arr\Access::keys() To get the union member identifiers.
     * @uses \FireHub\Runtime\Arr\Access::firstKey() To get the first union member identifier.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionUnionType $type <p>
     * Declared union type.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates are eligible.
     *
     * @return null|class-string Selected service identifier, or null if no candidate is available.
     */
    private function unionId (ReflectionParameter $parameter, ReflectionUnionType $type):?string {

        $registered = [];
        $implicit = [];

        foreach ($type->getTypes() as $member) {

            if (!$member instanceof ReflectionNamedType)
                continue;

            $id = $this->classId($parameter, $member);

            if ($id === null)
                continue;

            if (($this->has)($id)) {

                $registered[$id] = true;

                continue;

            }

            if ($this->canResolve($id))
                $implicit[$id] = true;

        }

        $candidates = $registered !== [] ? $registered : $implicit;

        if (Runtime\Arr\Inspection::count($candidates) > 1)
            throw new AmbiguousDependencyException(
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName(),
                    'type' => (string)$type,
                    'candidates' => Runtime\Arr\Access::keys($candidates)
                ]
            );

        return Runtime\Arr\Access::firstKey($candidates);

    }

    /**
     * ### Resolves an intersection dependency identifier
     *
     * Selects a registered service whose implementation satisfies every declared type in the intersection.
     *
     * Resolution succeeds only when exactly one eligible candidate exists.
     *
     * This method does not instantiate any dependencies.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::classId() Resolves a parameter class identifier.
     * @uses \FireHub\Runtime\Arr\Inspection::count() To check if the intersection contains multiple candidates.
     * @uses \FireHub\Runtime\Arr\Access::keys() To get the intersection member identifiers.
     * @uses \FireHub\Runtime\Arr\Access::firstKey() To get the first intersection member identifier.
     * @uses \FireHub\Runtime\ObjectModel\Relation::instanceOf() To check if a service implements a contract.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     * @param ReflectionIntersectionType $type <p>
     * Declared intersection type.
     * </p>
     *
     * @throws \FireHub\Container\Exception\AmbiguousDependencyException If multiple candidates satisfy the intersection.
     *
     * @return null|class-string Selected service identifier, or null if none matches.
     */
    private function intersectionId (ReflectionParameter $parameter, ReflectionIntersectionType $type):?string {

        $matches = [];

        foreach (($this->candidates)() as $id => $implementation) {

            $matchesAll = true;

            foreach ($type->getTypes() as $member) {

                if (!$member instanceof ReflectionNamedType)
                    continue;

                $contract = $this->classId($parameter, $member);

                if ($contract === null
                    || !Runtime\ObjectModel\Relation::instanceOf(
                        $implementation,
                        $contract
                    )) {

                    $matchesAll = false;

                    break;

                }

            }

            if ($matchesAll)
                $matches[$id] = true;

        }

        if (Runtime\Arr\Inspection::count($matches) > 1)
            throw new AmbiguousDependencyException(
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName(),
                    'type' => (string)$type,
                    'candidates' => Runtime\Arr\Access::keys($matches)
                ]
            );

        return Runtime\Arr\Access::firstKey($matches);

    }

    /**
     * ### Resolves a constructor parameter
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::canResolve() Checks if a dependency can be resolved.
     * @uses \FireHub\Container\Internal\Autowirer::resolve() Resolves a dependency.
     * @uses \FireHub\Container\Internal\Autowirer::classId() Resolves a parameter class identifier.
     * @uses \FireHub\Container\Internal\Autowirer::unionId() Resolves a union dependency identifier.
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
            $type instanceof ReflectionNamedType
                => $this->classId($parameter, $type),
            $type instanceof ReflectionUnionType
                => $this->unionId($parameter, $type),
            $type instanceof ReflectionIntersectionType
                => $this->intersectionId($parameter, $type),
            default => null
        };

        if ($id !== null && $this->canResolve($id))
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