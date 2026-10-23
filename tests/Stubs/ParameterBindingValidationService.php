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
 * ### Parameter binding validation service
 * @since 1.0.0
 */
final readonly class ParameterBindingValidationService implements ParameterBindingValidationContract {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param int $port Port number.
     * @param null|string $host Host name.
     * @param float $timeout Timeout value.
     * @param null|ParameterBindingValidationContract $dependency Optional dependency.
     */
    public function __construct (
        public int $port = 3306,
        public ?string $host = null,
        public float $timeout = 1.0,
        public ?ParameterBindingValidationContract $dependency = null
    ) {}

}