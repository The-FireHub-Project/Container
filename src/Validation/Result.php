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

use FireHub\Runtime;
use Countable, IteratorAggregate, Traversable;

/**
 * ### Represents the result of container validation
 *
 * Collects errors and warnings discovered while statically analyzing registered service definitions.
 *
 * A validation result is considered valid when it contains no errors. Warnings do not invalidate the result.
 *
 * The result is immutable and supports iteration over all issues.
 * @since 1.0.0
 *
 * @implements IteratorAggregate<int, \FireHub\Container\Validation\Issue>
 */
final readonly class Result implements Countable, IteratorAggregate {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param list<\FireHub\Container\Validation\Issue> $issues [optional] <p>
     * Validation issues.
     * </p>
     */
    public function __construct (
        private array $issues = []
    ) {}

    /**
     * ### Checks whether validation succeeded
     *
     * Validation succeeds when no errors are present. Warnings do not cause validation failure.
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Issue::isError() To check for error status.
     *
     * @return bool True if no errors were detected.
     */
    public function isValid ():bool {

        foreach ($this->issues as $issue) {

            if ($issue->isError())
                return false;

        }

        return true;

    }

    /**
     * ### Checks whether warnings are present
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Issue::isWarning() To check for warning status.
     *
     * @return bool True if at least one warning exists.
     */
    public function hasWarnings ():bool {

        foreach ($this->issues as $issue) {

            if ($issue->isWarning())
                return true;

        }

        return false;

    }

    /**
     * ### Gets all validation issues
     * @since 1.0.0
     *
     * @return list<\FireHub\Container\Validation\Issue> Validation issues.
     */
    public function issues ():array {

        return $this->issues;

    }

    /**
     * ### Gets validation errors
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Issue::isError() To check for error status.
     *
     * @return list<\FireHub\Container\Validation\Issue> Validation errors.
     */
    public function errors ():array {

        $errors = [];

        foreach ($this->issues as $issue) {

            if ($issue->isError())
                $errors[] = $issue;

        }

        return $errors;

    }

    /**
     * ### Gets validation warnings
     * @since 1.0.0
     *
     * @uses \FireHub\Container\Validation\Issue::isWarning() To check for warning status.
     *
     * @return list<\FireHub\Container\Validation\Issue> Validation warnings.
     */
    public function warnings ():array {

        $warnings = [];

        foreach ($this->issues as $issue) {

            if ($issue->isWarning())
                $warnings[] = $issue;

        }

        return $warnings;

    }

    /**
     * ### Counts validation issues
     * @since 1.0.0
     *
     * @uses \FireHub\Runtime\Arr\Inspection::count() To count the number of issues.
     *
     * @return non-negative-int Number of validation issues.
     */
    public function count ():int {

        return Runtime\Arr\Inspection::count($this->issues);

    }

    /**
     * ### Iterates over validation issues
     * @since 1.0.0
     *
     * @return Traversable<int, \FireHub\Container\Validation\Issue> Validation issues.
     */
    public function getIterator ():Traversable {

        yield from $this->issues;

    }

}