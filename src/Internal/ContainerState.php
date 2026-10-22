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
    ContainerClosedException, ContainerFrozenException
};

/**
 * ### Manages dependency injection container lifecycle state
 *
 * Maintains the shared lifecycle state of a dependency injection container.
 *
 * The container supports three logical states:
 * - Open: Registration and resolution are permitted.
 * - Frozen: Registration is prohibited, but resolution remains permitted.
 * - Closed: Registration and resolution are permanently prohibited.
 *
 * Freezing is irreversible and does not affect existing service instances.
 *
 * Closing permanently terminates the container lifecycle regardless of whether the container was previously frozen.
 *
 * The state is shared between the container and its resolver to enforce consistent lifecycle restrictions.
 * @since 1.0.0
 *
 * @internal
 */
final class ContainerState {

    /**
     * ### Container freeze state
     * @since 1.0.0
     */
    private bool $frozen = false;

    /**
     * ### Container closure state
     * @since 1.0.0
     */
    private bool $closed = false;

    /**
     * ### Ensures the container is open
     *
     * Prevents operations requiring an active container after closure.
     * @since 1.0.0
     *
     * @throws \FireHub\Container\Exception\ContainerClosedException If the container is closed.
     *
     * @return void
     */
    public function assertOpen ():void {

        if ($this->closed)
            throw new ContainerClosedException;

    }

    /**
     * ### Ensures the container accepts configuration changes
     *
     * Prevents service registration and configuration changes when the container is frozen or closed.
     *
     * Closure takes precedence over freezing when both conditions apply.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContainerState::assertOpen()
     *
     * @throws \FireHub\Container\Exception\ContainerClosedException If the container is closed.
     * @throws \FireHub\Container\Exception\ContainerFrozenException If the container is frozen.
     *
     * @return void
     */
    public function assertMutable ():void {

        $this->assertOpen();

        if ($this->frozen)
            throw new ContainerFrozenException;

    }

    /**
     * ### Freezes the container configuration
     *
     * Permanently prevents further registration and configuration changes.
     *
     * Existing services remain available for resolution.
     *
     * Repeated calls have no additional effect.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ContainerState::assertOpen() To ensure the container is open.
     *
     * @throws \FireHub\Container\Exception\ContainerClosedException If the container is closed.
     *
     * @return void
     */
    public function freeze ():void {

        $this->assertOpen();

        $this->frozen = true;

    }

    /**
     * ### Checks whether the container is frozen
     * @since 1.0.0
     *
     * @return bool True if the container configuration has been frozen.
     */
    public function isFrozen ():bool {

        return $this->frozen;

    }

    /**
     * ### Closes the container lifecycle
     *
     * Permanently marks the container as closed.
     *
     * Repeated calls have no additional effect.
     * @since 1.0.0
     *
     * @return void
     */
    public function close ():void {

        $this->closed = true;

    }

    /**
     * ### Checks whether the container is closed
     * @since 1.0.0
     *
     * @return bool True if the container has been closed, false otherwise.
     */
    public function isClosed ():bool {

        return $this->closed;

    }

}