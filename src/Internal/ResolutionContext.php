<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.5
 * @package Container
 */

namespace FireHub\Container\Internal;

/**
 * ### Represents an active dependency resolution context
 *
 * Maintains the state associated with a single dependency resolution chain.
 *
 * The context tracks circular dependencies and provides access to the active scoped instance storage.
 *
 * A new context is created for each independent resolution request and shared by all nested resolutions
 * belonging to that request.
 * @since 1.0.0
 *
 * @internal
 */
final readonly class ResolutionContext {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Internal\ResolutionStack $stack <p>
     * Active dependency resolution stack.
     * </p>
     * @param null|\FireHub\Container\Internal\InstanceStore $scoped <p>
     * Active scoped instance storage, or null for root resolution.
     * </p>
     * @param null|\FireHub\Container\Internal\ScopeState $scope <p>
     * Associated scope lifecycle state.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public ResolutionStack $stack = new ResolutionStack,
        public ?InstanceStore $scoped = null,
        public ?ScopeState $scope = null
    ) {}

    /**
     * ### Creates a root resolution context
     *
     * Returns a context that does not provide access to scoped instances while preserving the current
     * dependency resolution stack.
     * @since 1.0.0
     *
     * @return self Root resolution context.
     */
    public function root ():self {

        if ($this->scoped === null)
            return $this;

        return new self(
            stack: $this->stack,
            scope: $this->scope
        );

    }

}