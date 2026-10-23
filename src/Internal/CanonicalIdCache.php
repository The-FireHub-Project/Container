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

use FireHub\Container\Registry;
use FireHub\Container\Definition\Alias;
use FireHub\Runtime;

/**
 * ### Caches canonical service identifiers
 *
 * Resolves registered alias chains to their final service identifiers.
 *
 * Successfully resolved identifiers and invalid alias chains are cached for reuse throughout a single container
 * validation operation.
 *
 * Circular and unresolved aliases return null.
 *
 * The cache assumes that service registrations remain unchanged during its lifetime.
 * @since 1.0.0
 *
 * @internal
 */
final class CanonicalIdCache {

    /**
     * ### Cached canonical identifiers
     * @since 1.0.0
     *
     * @var array<class-string, null|class-string>
     */
    private array $cache = [];

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Registry $registry <p>
     * Registered service definitions.
     * </p>
     */
    public function __construct (
        private readonly Registry $registry
    ) {}

    /**
     * ### Gets the canonical service identifier
     *
     * Follows alias chains and caches their resolved targets.
     *
     * Invalid and circular alias chains are cached as null.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Registry::find() To retrieve a service definition.
     * @uses \FireHub\Container\Registry::has() To check if an alias target exists.
     * @uses \FireHub\Runtime\Arr\Access::keyExists() To check if a key exists in an array.
     *
     * @param class-string $id <p>
     * Service identifier.
     * </p>
     *
     * @return null|class-string Canonical service identifier.
     */
    public function get (string $id):?string {

        if (Runtime\Arr\Access::keyExists($this->cache, $id))
            return $this->cache[$id]; // @phpstan-ignore offsetAccess.notFound

        $visited = []; $path = []; $current = $id;
        while (true) {

            if (Runtime\Arr\Access::keyExists($this->cache, $id)) { // @phpstan-ignore if.alwaysFalse

                $canonical = $this->cache[$current]; // @phpstan-ignore offsetAccess.notFound

                break;

            }

            if (isset($visited[$current])) {

                $canonical = null;

                break;

            }

            $visited[$current] = true;

            $path[] = $current;

            $definition = $this->registry->find($current);

            if (!$definition instanceof Alias) {

                $canonical = $current;

                break;

            }

            $current = $definition->target;

            if (!$this->registry->has($current)) {

                $canonical = null;

                break;

            }

        }

        foreach ($path as $visited_id)
            $this->cache[$visited_id] = $canonical;

        return $canonical;

    }

}