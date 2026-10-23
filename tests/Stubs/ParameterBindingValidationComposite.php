<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.2
 * @package Container\Tests
 */

namespace FireHub\Tests\Container\Stubs;

/**
 * ### Composite parameter binding validation service
 * @since 1.0.0
 */
final readonly class ParameterBindingValidationComposite {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param int|string $union Union value.
     * @param \Countable&\IteratorAggregate $intersection Intersection value.
     * @param (\Countable&\IteratorAggregate)|string $dnf DNF value.
     */
    public function __construct (
        public int|string $union,
        public \Countable&\IteratorAggregate $intersection,
        public (\Countable&\IteratorAggregate)|string $dnf
    ) {}

}