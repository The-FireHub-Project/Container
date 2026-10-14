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

use FireHub\Container\Exception\CircularDependencyException;
use FireHub\Runtime;

/**
 * ### Tracks active service resolutions
 *
 * Detects circular dependencies by tracking service identifiers currently being resolved.
 * @since 1.0.0
 *
 * @internal
 */
final class ResolutionStack {

    /**
     * ### Service resolution stack
     * @since 1.0.0
     *
     * @var list<class-string>
     */
    private array $stack = [];

    /**
     * ### Starts resolving a service
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::inArray() Checks if the service is already being resolved.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @throws \FireHub\Container\Exception\CircularDependencyException If the service is already being resolved.
     *
     * @return void
     */
    public function enter (string $id):void {

        if (Runtime\Arr\Inspection::inArray($this->stack, $id))
            throw new CircularDependencyException(
                context: [
                    'stack' => $this->stack
                ]
            );

        $this->stack[] = $id;

    }

    /**
     * ### Finishes resolving the current service
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Mutation::pop() Removes the last element from the array.
     *
     * @return void
     */
    public function leave ():void {

        Runtime\Arr\Mutation::pop($this->stack);

    }

}