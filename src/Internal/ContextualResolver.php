<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.2
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Core\Boundary\Resolver;
use Closure;

/**
 * ### Resolves dependencies within an existing resolution context
 *
 * Preserves the active resolution context across nested factory resolutions.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ContextualResolver implements Resolver {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param Closure(class-string):object $resolve <p>
     * Service resolver.
     * </p>
     * @param Closure(class-string):bool $has <p>
     * Service existence checker.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Closure $resolve,
        private Closure $has
    ) {}

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     *
     * @template TService of object
     */
    public function get (string $id):object {

        /** @var TService */
        return ($this->resolve)($id); // @phpstan-ignore return.type

    }

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    public function has (string $id):bool {

        return ($this->has)($id);

    }

}