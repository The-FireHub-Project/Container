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

namespace FireHub\Container\Validation;

/**
 * ### Represents a container validation issue
 *
 * Describes a configuration problem or warning discovered during static analysis of service definitions.
 *
 * Validation issues are immutable and contain enough diagnostic information to identify the affected service and
 * dependency path.
 * @since 1.0.0
 *
 * @phpstan-type DependencyPath list<non-empty-string>
 */
final readonly class Issue {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Container\Validation\IssueType $type <p>
     * Category of the validation issue.
     * </p>
     * @param \FireHub\Container\Validation\Severity $severity <p>
     * Severity of the validation issue.
     * </p>
     * @param non-empty-string $service <p>
     * Identifier of the affected service.
     * </p>
     * @param non-empty-string $message <p>
     * Human-readable diagnostic message.
     * </p>
     * @param list<non-empty-string> $path [optional] <p>
     * Dependency path associated with the issue.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public IssueType $type,
        public Severity $severity,
        public string $service,
        public string $message,
        public array $path = []
    ) {}

    /**
     * ### Checks whether the issue is an error
     * @since 1.0.0
     *
     * @return bool True if the issue represents an error.
     */
    public function isError ():bool {

        return $this->severity === Severity::ERROR;

    }

    /**
     * ### Checks whether the issue is a warning
     * @since 1.0.0
     *
     * @return bool True if the issue represents a warning.
     */
    public function isWarning ():bool {

        return $this->severity === Severity::WARNING;

    }

}