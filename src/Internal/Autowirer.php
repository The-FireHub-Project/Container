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
use FireHub\Runtime;
use Closure, ReflectionClass, ReflectionException, ReflectionNamedType, ReflectionParameter;

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
     */
    public function __construct (
        private Closure $resolve,
        private Closure $has
    ) {}

    /**
     * ### Constructs a service with resolved dependencies
     *
     * Inspects the constructor and resolves registered object dependencies.
     *
     * Optional parameters with unresolved dependencies use their default values when available.
     * @since 1.0.0
     *
     * @param class-string $class <p>
     * Concrete service class.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service is not registered.
     * @throws ReflectionException If the class cannot be reflected.
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

        foreach ($constructor->getParameters() as $parameter)
            $arguments[] = $this->resolveParameter($parameter);

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
     * ### Resolves a constructor parameter
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\Autowirer::canResolve() Checks if a dependency can be resolved.
     * @uses \FireHub\Container\Internal\Autowirer::resolve() Resolves a dependency.
     * @uses \FireHub\Container\Internal\Autowirer::classId() Resolves a parameter class identifier.
     *
     * @param ReflectionParameter $parameter <p>
     * Constructor parameter.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceInstantiationException If the parameter is not optional and cannot
     * be resolved.
     *
     * @return mixed Resolved argument.
     */
    private function resolveParameter (ReflectionParameter $parameter):mixed {

        if ($parameter->isVariadic())
            throw new ServiceInstantiationException(
                context: [
                    'class' => $parameter->getDeclaringClass()?->getName(),
                    'parameter' => $parameter->getName()
                ]
            );

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {

            $id = $this->classId($parameter, $type);

            if ($id !== null && $this->canResolve($id))
                return ($this->resolve)($id);

        }

        if ($parameter->isDefaultValueAvailable())
            return $parameter->getDefaultValue();

        throw new ServiceInstantiationException(
            context: [
                'class' => $parameter->getDeclaringClass()?->getName(),
                'parameter' => $parameter->getName()
            ]
        );

    }

}