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
 * ### Defines container validation severity levels
 *
 * Distinguishes confirmed configuration errors from warnings that do not necessarily prevent successful service
 * resolution.
 * @since 1.0.0
 */
enum Severity {

    /**
     * ### Error
     *
     * Represents a confirmed configuration problem.
     * @since 1.0.0
     */
    case ERROR;

    /**
     * ### Warning
     *
     * Represents a potential problem or a limitation of static validation.
     * @since 1.0.0
     */
    case WARNING;

}