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

namespace FireHub\Container\Attribute;

use Attribute;

/**
 * ### Marks a constructor parameter for tagged service injection
 *
 * Declares that a constructor parameter should receive all services associated with the specified tag.
 *
 * Tagged services are resolved in their registration order and retain their configured singleton, scoped, or
 * transient lifetimes.
 *
 * The attribute is intended for array-typed constructor parameters.
 *
 * <code>
 * use FireHub\Container\Attribute\Tagged;
 *
 * final readonly class MiddlewarePipeline {
 *
 *     public function __construct (
 *         #[Tagged('http.middleware')]
 *         public array $middlewares
 *     ) {}
 *
 * }
 * </code>
 *
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Tagged {

    /**
     * ### Constructor
     *
     * <code>
     * use FireHub\Container\Attribute\Tagged;
     *
     * $attribute = new Tagged('http.middleware');
     *
     * $attribute->tag; // 'http.middleware'
     * </code>
     *
     * @since 1.0.0
     *
     * @param non-empty-string $tag <p>
     * Name of the service tag to resolve.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public string $tag
    ) {}

}