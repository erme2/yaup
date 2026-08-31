<?php

declare(strict_types=1);

namespace Yaup\Ticket;

final readonly class TicketStatus
{
    /**
     * @param list<string> $matchingRefs
     * @param list<string> $plans
     */
    public function __construct(
        public string $project,
        public string $path,
        public string $checkout,
        public string $branch,
        public array $matchingRefs,
        public array $plans,
        public string $worktree,
    ) {}
}
