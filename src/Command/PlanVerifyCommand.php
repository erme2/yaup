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
use Yaup\Plan\PlanVerifier;

#[AsCommand(name: 'plan:verify', description: 'Verify an approved plan is committed and unchanged')]
final class PlanVerifyCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('plan', InputArgument::REQUIRED);
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plan = $input->getArgument('plan');
        if (!is_string($plan)) {
            return Command::INVALID;
        }
        $verification = (new PlanVerifier(new ConfigLoader()))->verify($this->root, $plan);
        if (!$verification->valid) {
            $io->error($verification->errors);
            return Command::FAILURE;
        }
        $io->success('Plan approval verified at ' . $verification->approvalCommit);
        return Command::SUCCESS;
    }
}
