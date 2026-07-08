<?php

declare(strict_types=1);

namespace Yaup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Yaup\Config\ConfigLoader;
use Yaup\Rules\RuleResolver;

#[AsCommand(name: 'rules:resolve', description: 'Print effective structured rules and discovered native instructions')]
final class RulesResolveCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('project', InputArgument::REQUIRED)->addOption('target', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $input->getArgument('project');
        if (!is_string($project)) {
            return Command::INVALID;
        }
        $target = $input->getOption('target');
        $resolved = (new RuleResolver(new ConfigLoader()))->resolve($this->root, $project, is_string($target) ? $target : null);
        $output->writeln(Yaml::dump(['rules' => $resolved->rules, 'native_instructions' => $resolved->nativeFiles, 'conflicts' => $resolved->conflicts], 8, 2));

        return [] === $resolved->conflicts ? Command::SUCCESS : Command::FAILURE;
    }
}
