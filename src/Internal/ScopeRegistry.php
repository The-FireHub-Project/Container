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

use FireHub\Container\Scope;
use SplObjectStorage;

/**
 * ### Tracks active dependency injection scopes
 *
 * Maintains a registry of scopes belonging to a dependency injection container.
 *
 * Scopes are registered when created and removed when closed.
 *
 * Weak references prevent the registry from retaining scopes solely because they
 * were registered.
 *
 * The registry does not control the scope lifecycle or dispose of scoped services.
 * @since 1.0.0
 */
final class ScopeRegistry {

    /**
     * ### Registered scopes
     * @since 1.0.0
     *
     * @var SplObjectStorage<\FireHub\Container\Scope, null>
     */
    private SplObjectStorage $scopes;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @return void
     */
    public function __construct () {

        $this->scopes = new SplObjectStorage;

    }

    /**
     * ### Registers a scope
     * @since 1.0.0
     *
     * @param \FireHub\Container\Scope $scope <p>
     * Scope to register.
     * </p>
     *
     * @return void
     */
    public function register (Scope $scope):void {

        $this->scopes->offsetSet($scope);

    }

    /**
     * ### Removes a scope from the registry
     *
     * @since 1.0.0
     *
     * @param \FireHub\Container\Scope $scope <p>
     * Scope to remove.
     * </p>
     *
     * @return void
     */
    public function unregister (Scope $scope):void {

        $this->scopes->offsetUnset($scope);

    }

    /**
     * ### Counts registered scopes
     * @since 1.0.0
     *
     * @return int Number of registered scopes.
     */
    public function count ():int {

        return $this->scopes->count();

    }

    /**
     * ### Checks whether active scopes exist
     * @since 1.0.0
     *
     * @return bool True if at least one scope is registered.
     */
    public function hasActive ():bool {

        return $this->scopes->count() > 0;

    }

}