<?php

declare(strict_types=1);

namespace Yaup;

use Symfony\Component\Console\Application as SymfonyApplication;
use Yaup\Command\AgentCommand;
use Yaup\Command\DiscoverCommand;
use Yaup\Command\InstructionsSyncCommand;
use Yaup\Command\PlanVerifyCommand;
use Yaup\Command\RulesResolveCommand;
use Yaup\Command\ValidateCommand;

final class Application extends SymfonyApplication
{
    public function __construct(string $root)
    {
        parent::__construct('yaup', '0.1.0-dev');
        $this->addCommands([
            new DiscoverCommand($root),
            new RulesResolveCommand($root),
            new PlanVerifyCommand($root),
            new AgentCommand($root),
            new ValidateCommand(),
            new InstructionsSyncCommand($root),
        ]);
    }
}
