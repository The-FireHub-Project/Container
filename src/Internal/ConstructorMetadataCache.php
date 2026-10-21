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

use ReflectionClass;

/**
 * ### Caches constructor reflection metadata
 *
 * Stores constructor metadata for service implementation classes to avoid repeated reflection inspection.
 *
 * Metadata is cached per resolver instance and reused across dependency resolution operations.
 *
 * The cache does not store service instances, resolved dependencies, or registration-dependent candidate selections.
 * @since 1.0.0
 *
 * @internal
 */
final class ConstructorMetadataCache {

    /**
     * ### Cached constructor metadata
     * @since 1.0.0
     *
     * @var array<class-string, \FireHub\Container\Internal\ConstructorMetadata>
     */
    private array $metadata = [];

    /**
     * ### Gets constructor metadata
     *
     * Returns previously inspected constructor metadata or creates and caches it on first access.
     *
     * Reflection inspection is performed only once for each implementation class within the cache lifetime.
     * @since 1.0.0
     *
     * @param class-string $class <p>
     * Service implementation class.
     * </p>
     *
     * @return \FireHub\Container\Internal\ConstructorMetadata Cached constructor metadata.
     */
    public function get (string $class):ConstructorMetadata {

        if (isset($this->metadata[$class]))
            return $this->metadata[$class];

        $reflection = new ReflectionClass($class);

        $constructor = $reflection->getConstructor();

        return $this->metadata[$class] = new ConstructorMetadata(
            $reflection,
            $reflection->isInstantiable(),
            $constructor !== null,
            $constructor?->getParameters() ?? []
        );

    }

}