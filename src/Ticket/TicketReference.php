<?php

declare(strict_types=1);

namespace Yaup\Ticket;

final readonly class TicketReference
{
    public function __construct(
        public string $input,
        public string $number,
    ) {}

    public static function fromInput(string $input): self
    {
        if (1 === preg_match('~(?:issues|pull)/(\d+)(?:\D|$)~', $input, $matches)) {
            return new self($input, $matches[1]);
        }

        if (1 === preg_match('~#(\d+)\b~', $input, $matches)) {
            return new self($input, $matches[1]);
        }

        if (1 === preg_match('~^\d+$~', $input)) {
            return new self($input, $input);
        }

        return new self($input, $input);
    }

    public function matches(string $value): bool
    {
        if ($this->input !== $this->number && str_contains($value, $this->input)) {
            return true;
        }

        return 1 === preg_match('~(?:^|[^\d])' . preg_quote($this->number, '~') . '(?:[^\d]|$)~', $value);
    }
}
