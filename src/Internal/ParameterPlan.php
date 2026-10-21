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

use ReflectionParameter, ReflectionUnionType, ReflectionIntersectionType;

/**
 * ### Represents a compiled constructor parameter plan
 *
 * Stores the statically determined information required to resolve a constructor parameter.
 *
 * Dependency availability and candidate selection remain dynamic and are evaluated during plan execution.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ParameterPlan {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param ReflectionParameter $parameter <p>
     * Original constructor parameter.
     * </p>
     * @param null|class-string $id <p>
     * Statically resolved named dependency identifier.
     * </p>
     * @param null|ReflectionUnionType|ReflectionIntersectionType $composite <p>
     * Composite dependency type requiring dynamic candidate selection.
     * </p>
     * @param bool $hasDefault <p>
     * Whether the parameter provides a default value.
     * </p>
     * @param mixed $default <p>
     * Default parameter value.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public ReflectionParameter $parameter,
        public ?string $id,
        public ReflectionUnionType|ReflectionIntersectionType|null $composite,
        public bool $hasDefault,
        public mixed $default
    ) {}

}