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

use FireHub\Core\Boundary\Lifecycle\Disposable;
use Throwable;

/**
 * ### Tracks and disposes of owned resources
 *
 * Maintains a registry of disposable service instances owned by a dependency injection lifecycle.
 *
 * Each instance is registered only once using object identity. Resources are disposed of in reverse registration
 * order, allowing dependent services to release their resources before the services they depend on.
 *
 * Disposal attempts to continue even when individual instances fail to release their resources. The first encountered
 * exception is propagated after all registered instances have been processed.
 *
 * The tracker does not determine service ownership or lifetime. These responsibilities belong to the container or
 * scope managing the tracker.
 *
 * Once disposal begins, the tracker cannot accept additional instances.
 * @since 1.0.0
 *
 * @internal
 */
final class DisposalTracker {

    /**
     * ### Registered disposable instances
     * @since 1.0.0
     *
     * @var list<\FireHub\Core\Boundary\Lifecycle\Disposable>
     */
    private array $instances = [];

    /**
     * ### Registered instance identities
     * @since 1.0.0
     *
     * @var array<int, true>
     */
    private array $identities = [];

    /**
     * ### Whether disposal has started
     * @since 1.0.0
     */
    private bool $disposed = false;

    /**
     * ### Registers a disposable instance
     *
     * Adds an instance to the disposal registry unless the same object has already been registered.
     *
     * Registration is rejected after disposal has started.
     * @since 1.0.0
     *
     * @param \FireHub\Core\Boundary\Lifecycle\Disposable $instance <p>
     * Disposable instance to register.
     * </p>
     *
     * @throws \LogicException If registration is attempted after disposal has started.
     *
     * @return void
     */
    public function track (Disposable $instance):void {

        if ($this->disposed)
            throw new \LogicException(
                'Cannot track disposable instances after disposal has started.'
            );

        $id = spl_object_id($instance);

        if (isset($this->identities[$id])) return;

        $this->identities[$id] = true;

        $this->instances[] = $instance;

    }

    /**
     * ### Disposes of all tracked instances
     *
     * Releases registered resources in reverse registration order.
     *
     * Every instance is attempted even when disposal of another instance fails.
     *
     * Repeated calls have no effect.
     * @since 1.0.0
     *
     * @uses \FireHub\Core\Boundary\Lifecycle\Disposable::dispose() To release instance resources.
     *
     * @throws Throwable If one or more instances fail during disposal.
     *
     * @return void
     */
    public function dispose ():void {

        if ($this->disposed) return;

        $this->disposed = true;

        $failure = null;

        for ($index = count($this->instances) - 1; $index >= 0; $index--) {

            try {

                $this->instances[$index]->dispose();

            } catch (Throwable $exception) {

                $failure ??= $exception;

            }

        }

        $this->instances = [];
        $this->identities = [];

        if ($failure !== null)
            throw $failure;

    }

}