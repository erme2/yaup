<?php

declare(strict_types=1);

namespace Yaup\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaup\Config\ConfigLoader;
use Yaup\Plan\PlanPreview;

#[AsCommand(name: 'plan:diff', description: 'Preview the human-readable scope described by a plan')]
final class PlanDiffCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('plan', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $planPath = $input->getArgument('plan');
        if (!is_string($planPath)) {
            return Command::INVALID;
        }

        try {
            $plan = (new ConfigLoader())->load($planPath);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $output->write((new PlanPreview())->render($plan));

        return Command::SUCCESS;
    }
}
