<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container
 */

namespace FireHub\Container\Internal;

use FireHub\Runtime;

/**
 * ### Stores service tag registrations
 *
 * Maintains ordered associations between tag names and explicitly registered service identifiers.
 *
 * Each service identifier can belong to multiple tags, while duplicate registrations within the same tag are
 * ignored. Registration order is preserved for deterministic service collection resolution.
 *
 * This registry stores only service identifiers and does not instantiate services, manage lifetimes, or resolve
 * dependencies.
 * @since 1.0.0
 *
 * @internal
 */
final class TagRegistry {

    /**
     * ### Registered service tags
     *
     * @var array<non-empty-string, list<class-string>>
     */
    private array $tags = [];

    /**
     * ### Registers service identifiers under a tag
     *
     * Associates the specified service identifiers with the given tag while preserving registration order.
     * Duplicate identifiers within the same tag are ignored.
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::inArray() To check if the service identifier is already registered.
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     * @param class-string ...$services <p>
     * Registered service identifiers.
     * </p>
     *
     * @return void
     */
    public function register (string $tag, string ...$services):void {

        foreach ($services as $service) {

            if (Runtime\Arr\Inspection::inArray($this->tags[$tag] ?? [], $service))
                continue;

            $this->tags[$tag][] = $service;

        }

    }

    /**
     * ### Checks whether a tag contains registered services
     * @since 1.0.0
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     *
     * @return bool True if the tag contains at least one service, otherwise false.
     */
    public function has (string $tag):bool {

        return isset($this->tags[$tag]);

    }

    /**
     * ### Retrieves service identifiers associated with a tag
     *
     * Returns registered service identifiers in their original registration order.
     * @since 1.0.0
     *
     * @param non-empty-string $tag <p>
     * Tag name.
     * </p>
     *
     * @return list<class-string> Registered service identifiers.
     */
    public function get (string $tag):array {

        return $this->tags[$tag] ?? [];

    }

}