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
     * ### Resolves a constructor parameter
     * @since 1.0.0
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

            /** @var class-string $id */
            $id = match ($type->getName()) {
                'self' => $parameter->getDeclaringClass()?->getName(),
                'parent' => $parameter->getDeclaringClass()?->getParentClass()?->getName(), // @phpstan-ignore method.nonObject
                default => $type->getName()
            };

            if ($id !== null && ($this->has)($id))
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