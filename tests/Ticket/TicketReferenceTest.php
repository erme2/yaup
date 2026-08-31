<?php

declare(strict_types=1);

namespace Yaup\Tests\Ticket;

use PHPUnit\Framework\TestCase;
use Yaup\Ticket\TicketReference;

final class TicketReferenceTest extends TestCase
{
    public function testExtractsIssueNumberFromUrl(): void
    {
        $ticket = TicketReference::fromInput('https://github.com/erme2/yaup/issues/20');

        self::assertSame('20', $ticket->number);
        self::assertTrue($ticket->matches('feature/20-cross-repo-status'));
        self::assertTrue($ticket->matches('Work on https://github.com/erme2/yaup/issues/20'));
        self::assertFalse($ticket->matches('feature/120-other-work'));
    }

    public function testPlainTicketNumberDoesNotMatchIncidentalSubstrings(): void
    {
        $ticket = TicketReference::fromInput('20');

        self::assertTrue($ticket->matches('feature/20-cross-repo-status'));
        self::assertFalse($ticket->matches('approved_at: 2026-08-31T10:30:00+01:00'));
    }
}
