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

/**
 * ### Stores resolved service instances
 *
 * Maintains a collection of resolved service instances indexed by their registered service identifiers.
 * @since 1.0.0
 *
 * @internal
 */
final class InstanceStore {

    /**
     * ### Service instances
     * @since 1.0.0
     *
     * @var array<class-string, object>
     */
    private array $instances = [];

    /**
     * ### Checks whether an instance exists
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return bool True if the instance is stored.
     */
    public function has (string $id):bool {

        return isset($this->instances[$id]);

    }

    /**
     * ### Gets a stored instance
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return null|object Stored instance, or null if absent.
     */
    public function get (string $id):?object {

        return $this->instances[$id] ?? null;

    }

    /**
     * ### Stores a service instance
     * @since 1.0.0
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     * @param object $instance <p>
     * Service instance.
     * </p>
     *
     * @return void
     */
    public function set (string $id, object $instance):void {

        $this->instances[$id] = $instance;

    }

    /**
     * ### Releases stored instances
     * @since 1.0.0
     *
     * @return void
     */
    public function clear ():void {

        $this->instances = [];

    }

}