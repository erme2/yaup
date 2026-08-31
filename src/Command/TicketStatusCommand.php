<?php

declare(strict_types=1);

namespace Yaup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaup\Config\ConfigLoader;
use Yaup\Ticket\TicketReference;
use Yaup\Ticket\TicketStatus;
use Yaup\Ticket\TicketStatusReporter;

#[AsCommand(name: 'ticket:status', description: 'Show local cross-repository status for a ticket')]
final class TicketStatusCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('ticket', InputArgument::REQUIRED, 'Ticket number, #number, or issue URL');
        $this->addArgument('project', InputArgument::IS_ARRAY, 'Optional registered project names to include');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ticketInput = $input->getArgument('ticket');
        if (!is_string($ticketInput) || '' === trim($ticketInput)) {
            $io->error('ticket must be a non-empty string.');
            return Command::INVALID;
        }

        $projectArguments = $input->getArgument('project');
        if (!is_array($projectArguments)) {
            $io->error('project filters must be strings.');
            return Command::INVALID;
        }

        $selectedProjects = [];
        foreach ($projectArguments as $project) {
            if (!is_string($project)) {
                $io->error('project filters must be strings.');
                return Command::INVALID;
            }

            $selectedProjects[] = $project;
        }

        try {
            $statuses = (new TicketStatusReporter($this->root, new ConfigLoader()))->report(TicketReference::fromInput($ticketInput), $selectedProjects);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->table(
            ['Project', 'Checkout', 'Branch', 'Ticket refs', 'Plans', 'Worktree'],
            array_map(
                static fn(TicketStatus $status): array => [
                    $status->project,
                    $status->checkout,
                    $status->branch,
                    [] === $status->matchingRefs ? '-' : implode("\n", $status->matchingRefs),
                    [] === $status->plans ? '-' : implode("\n", $status->plans),
                    $status->worktree,
                ],
                $statuses,
            ),
        );

        return Command::SUCCESS;
    }
}
