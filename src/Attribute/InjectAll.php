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
 * ### Marks a constructor parameter for collection injection
 *
 * Automatically injects all explicitly registered services compatible with the specified type.
 *
 * Services are resolved in registration order while preserving their configured lifetimes and the active resolution
 * context.
 *
 * If no matching services are registered, an empty array is injected.
 *
 * Only non-nullable array constructor parameters are supported.
 *
 * <code>
 * use FireHub\Container\Attribute\InjectAll;
 *
 * final readonly class LoggerManager {
 *
 *     public function __construct(
 *         #[InjectAll(LoggerInterface::class)]
 *         public array $loggers
 *     ) {}
 *
 * }
 * </code>
 *
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class InjectAll {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param class-string $type <p>
     * Required service type.
     * </p>
     *
     * @return void
     */
    public function __construct(
        public string $type
    ) {}

}