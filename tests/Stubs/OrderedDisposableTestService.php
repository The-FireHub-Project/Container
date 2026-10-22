<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container\Tests
 */

namespace FireHub\Tests\Container\Stubs;

use FireHub\Core\Boundary\Lifecycle\Disposable;

/**
 * ### Ordered disposable test service
 *
 * Records disposal order using a shared event collection.
 * @since 1.0.0
 */
class OrderedDisposableTestService implements Disposable {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param string $name Service name.
     * @param array<int, string> $events Shared disposal events.
     */
    public function __construct (
        public string $name,
        private array &$events
    ) {}

    /**
     * ### Records disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function dispose ():void {

        $this->events[] = $this->name;

    }

}