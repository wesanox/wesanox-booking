<?php

declare(strict_types=1);

namespace Wesanox\Booking\Support;

defined('ABSPATH') || exit;

/**
 * Carries one or more validation error messages from Application Services.
 */
final class ValidationException extends \RuntimeException
{
    /** @var string[] */
    private array $errors;

    /** @param string[] $errors */
    private function __construct(array $errors)
    {
        parent::__construct(implode('; ', $errors));
        $this->errors = $errors;
    }

    /** @param string[] $errors */
    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }
}
