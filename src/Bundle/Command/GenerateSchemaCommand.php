<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Talleu\RedisOm\Command\GenerateSchema;
use Talleu\RedisOm\Om\RedisObjectManager;

#[AsCommand(name: 'redis-om:generate:schema', description: 'Generate Redis schema from Redis OM metadata')]
class GenerateSchemaCommand extends Command
{
    public function __construct(private readonly RedisObjectManager $manager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('dir', InputArgument::REQUIRED, 'The directory where to search for PHP Redis OM metadata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        GenerateSchema::generateSchema($input->getArgument('dir'), $this->manager);

        return 0;
    }
}
