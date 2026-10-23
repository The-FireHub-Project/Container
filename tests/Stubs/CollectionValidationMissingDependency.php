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
 * ### Collection member with an unresolved dependency
 * @since 1.0.0
 */
final readonly class CollectionValidationMissingDependency implements CollectionValidationContract {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param CollectionValidationMissingContract $dependency Missing dependency.
     */
    public function __construct (
        public CollectionValidationMissingContract $dependency
    ) {}

}
