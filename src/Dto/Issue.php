<?php

namespace Portavice\FileSanitizer\Dto;

use JsonSerializable;
use Portavice\FileSanitizer\Enums\IssueSeverity;
use Stringable;

final class Issue implements JsonSerializable, Stringable
{
    public function __construct(public readonly string $code, public readonly string $message, public readonly IssueSeverity $severity = IssueSeverity::Warning)
    {
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s: %s', $this->severity->value, $this->code, $this->message);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity->value,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
