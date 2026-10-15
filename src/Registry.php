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

namespace FireHub\Container;

use FireHub\Container\Definition\ {
    Cls, Instance
};
use FireHub\Container\Exception\ {
    DuplicateServiceException, ServiceNotFoundException
};

/**
 * ### Manages registered service definitions
 *
 * Provides centralized storage and retrieval of dependency injection service definitions.
 *
 * Each service definition is associated with a unique class or interface identifier. Duplicate registrations
 * are rejected to prevent accidental replacement of existing service definitions.
 *
 * The registry does not construct service instances, execute factories, or manage service lifetimes.
 * @since 1.0.0
 *
 * @internal
 */
final class Registry {

    /**
     * ### Registered service definitions
     * @since 1.0.0
     *
     * @var array<class-string, \FireHub\Container\Definition>
     */
    private array $definitions = [];

    /**
     * ### Registers a service definition
     *
     * Associates the specified service identifier with its definition.
     *
     * An identifier may only be registered once within the registry.
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     * @param \FireHub\Container\Definition $definition <p>
     * Service definition.
     * </p>
     *
     * @throws \FireHub\Container\Exception\DuplicateServiceException If the service identifier is already registered.
     *
     * @return void
     */
    public function register (string $id, Definition $definition):void {

        if (isset($this->definitions[$id]))
            throw new DuplicateServiceException;

        $this->definitions[$id] = $definition;

    }

    /**
     * ### Gets a registered service definition
     *
     * Returns the definition associated with the specified service identifier without resolving the service.
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\ServiceNotFoundException If the service identifier is not registered.
     *
     * @return \FireHub\Container\Definition Registered service definition.
     */
    public function get (string $id):Definition {

        return $this->definitions[$id]
            ?? throw new ServiceNotFoundException;

    }

    /**
     * ### Checks whether a service is registered
     *
     * Determines whether a definition exists for the specified service identifier.
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return bool True if the service identifier is registered.
     */
    public function has (string $id):bool {

        return isset($this->definitions[$id]);

    }

    /**
     * ### Gets all registered service definitions
     *
     * Returns all registered service definitions indexed by their service identifiers.
     * @since 1.0.0
     *
     * @return array<class-string, \FireHub\Container\Definition> Registered service definitions.
     */
    public function all ():array {

        return $this->definitions;

    }

    /**
     * ### Gets registered service implementation types
     *
     * Returns service identifiers mapped to their statically known implementation classes.
     *
     * Factory definitions without declared implementation types are excluded.
     * @since 1.0.0
     *
     * @return array<string, class-string> Registered implementation types.
     */
    public function implementations ():array {

        $implementations = [];

        foreach ($this->definitions as $id => $definition) {

            if ($definition instanceof Cls)
                $implementations[$id] = $definition->class;

            elseif ($definition instanceof Instance)
                $implementations[$id] = $definition->instance::class;

        }

        return $implementations;

    }

}