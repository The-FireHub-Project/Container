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
    ScopeActiveException, ScopeClosedException
};

/**
 * ### Manages dependency injection scope lifecycle
 *
 * Tracks whether a scope is closed and prevents its closure while service resolution is active.
 * @since 1.0.0
 *
 * @internal
 */
final class ScopeState {

    /**
     * ### Scope closure state
     * @since 1.0.0
     */
    private bool $closed = false;

    /**
     * ### Active resolution count
     * @since 1.0.0
     */
    private int $active = 0;

    /**
     * ### Scope execution state
     * @since 1.0.0
     */
    private bool $running = false;

    /**
     * ### Begins a resolution operation
     *
     * Prevents resolution through a closed scope and increments
     * the number of active resolution operations.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::assertOpen() To check if the scope is open.
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return void
     */
    public function enter ():void {

        $this->assertOpen();

        $this->active++;

    }

    /**
     * ### Completes a resolution operation
     * @since 1.0.0
     *
     * @return void
     */
    public function leave ():void {

        if ($this->active > 0)
            $this->active--;

    }

    /**
     * ### Ensures the scope is open
     * @since 1.0.0
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     *
     * @return void
     */
    public function assertOpen ():void {

        if ($this->closed)
            throw new ScopeClosedException;

    }

    /**
     * ### Closes the scope
     *
     * Prevents closure while service resolution is active.
     * @since 1.0.0
     *
     * @throws \FireHub\Container\Exception\ScopeActiveException If service resolution is active.
     *
     * @return void
     */
    public function close ():void {

        if ($this->closed)
            return;

        if ($this->running || $this->active > 0)
            throw new ScopeActiveException;

        $this->closed = true;

    }

    /**
     * ### Checks whether the scope is closed
     * @since 1.0.0
     *
     * @return bool True if closed, false otherwise.
     */
    public function isClosed ():bool {

        return $this->closed;

    }

    /**
     * ### Begins scope execution
     *
     * Prevents concurrent or nested execution of the same scope.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Internal\ScopeState::assertOpen() To check if the scope is open.
     *
     * @throws \FireHub\Container\Exception\ScopeClosedException If the scope is closed.
     * @throws \FireHub\Container\Exception\ScopeActiveException If scope execution is already in progress.
     *
     * @return void
     */
    public function beginRun ():void {

        $this->assertOpen();

        if ($this->running || $this->active > 0)
            throw new ScopeActiveException;

        $this->running = true;

    }

    /**
     * ### Ends scope execution
     * @since 1.0.0
     *
     * @return void
     */
    public function endRun ():void {

        $this->running = false;

    }

}