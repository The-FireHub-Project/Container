<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.1
 * @package Container
 */

namespace FireHub\Container\Validation;

/**
 * ### Defines container validation issue types
 *
 * Categorizes configuration and dependency problems discovered during static validation of dependency injection
 * service definitions.
 *
 * Validation issue types identify the nature of a detected problem independently of its severity, affected service,
 * diagnostic message, or dependency path.
 *
 * The validation process does not instantiate services, execute factories, or modify container configuration.
 *
 * Some issues represent confirmed configuration errors, while others indicate limitations of static analysis.
 * Their severity is determined separately through ValidationSeverity.
 * @since 1.0.0
 */
enum IssueType {

    /**
     * ### Missing class
     *
     * A referenced class, interface, or other required type cannot be found.
     * @since 1.0.0
     */
    case MISSING_CLASS;

    /**
     * ### Non-instantiable class
     *
     * A class-based service definition references a type that cannot be instantiated directly, such as an abstract
     * class or interface, without a suitable concrete implementation.
     * @since 1.0.0
     */
    case NON_INSTANTIABLE_CLASS;

    /**
     * ### Type mismatch
     *
     * A service definition or binding references an implementation that is incompatible with its declared service
     * type.
     * @since 1.0.0
     */
    case TYPE_MISMATCH;

    /**
     * ### Missing dependency
     *
     * A required dependency cannot be satisfied through registered bindings, explicit parameter bindings,
     * constructor defaults, or supported automatic resolution.
     * @since 1.0.0
     */
    case MISSING_DEPENDENCY;

    /**
     * ### Ambiguous dependency
     *
     * Multiple dependency resolution candidates satisfy a required type, and the available configuration does not
     * identify a unique implementation.
     *
     * This includes ambiguity involving union, intersection, and disjunctive normal form types.
     * @since 1.0.0
     */
    case AMBIGUOUS_DEPENDENCY;

    /**
     * ### Circular dependency
     *
     * A dependency graph contains a cycle that prevents the involved services from being constructed through
     * ordinary dependency resolution.
     * @since 1.0.0
     */
    case CIRCULAR_DEPENDENCY;

    /**
     * ### Invalid alias
     *
     * An alias references a target that cannot be resolved according to the container's alias resolution rules.
     * @since 1.0.0
     */
    case INVALID_ALIAS;

    /**
     * ### Circular alias
     *
     * Two or more service aliases form a resolution cycle without reaching a concrete service definition.
     * @since 1.0.0
     */
    case CIRCULAR_ALIAS;

    /**
     * ### Invalid parameter binding
     *
     * An explicit constructor parameter binding references an unknown parameter or provides a value that is
     * incompatible with the parameter's declared type.
     * @since 1.0.0
     */
    case INVALID_PARAMETER_BINDING;

    /**
     * ### Invalid contextual binding
     *
     * A contextual dependency binding references an invalid consumer, dependency, or implementation, or defines
     * a relationship incompatible with the dependency's declared type.
     * @since 1.0.0
     */
    case INVALID_CONTEXTUAL_BINDING;

    /**
     * ### Lifetime mismatch
     *
     * A service dependency relationship violates the container's lifetime rules.
     *
     * Lifetime compatibility must be evaluated according to the actual resolution and ownership semantics of
     * the container rather than assuming that every dependency between different lifetimes is invalid.
     * @since 1.0.0
     */
    case LIFETIME_MISMATCH;

    /**
     * ### Unverifiable factory
     *
     * A factory-based service definition cannot be fully validated without executing its factory callback.
     *
     * This issue indicates a limitation of static analysis rather than a confirmed configuration error.
     * @since 1.0.0
     */
    case UNVERIFIABLE_FACTORY;

}