<?php
declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Command;

use Aksonov\GraphqlGenerator\FileWriter;
use Aksonov\GraphqlGenerator\SchemaFetcher;
use Aksonov\GraphqlGenerator\SchemaParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

final class GenerateTypes extends Command
{
    const NAME = 'generate:types';

    private FileWriter $fileWriter;
    private SchemaParser $schemaParser;
    private SchemaFetcher $schemaFetcher;
    private Parser $configParser;

    public function __construct()
    {
        $this->configParser = new Parser();
        $this->fileWriter = new FileWriter();
        $this->schemaParser = new SchemaParser();
        $this->schemaFetcher = new SchemaFetcher();

        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'config',
                shortcut: 'c',
                mode: InputOption::VALUE_REQUIRED,
                default: './configuration.yaml',
            )
            ->addArgument(name: 'output', mode: InputArgument::OPTIONAL, default: './generated')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        $config = $this->configParser->parseFile($configPath);

        $output->writeln('<info>Generating GraphQL Types</info>');

        foreach ($config['sources'] as $apiType => $params) {
            $this->writeVerbose($input, $output, '<info>Generating GraphQL Types for ' . $apiType . '</info>');
            $schema = $this->schemaFetcher->getSchema($params['url'], $params['headers'] ?? []);
            $outDir = rtrim($input->getArgument('output'), '/') . "/$apiType";

            $this->fileWriter->emptyDir($outDir);

            foreach ($this->schemaParser->denormalizeSchema($schema) as $type) {
                $content = $this->fileWriter->typeToClass($params['namespace'], $type);

                file_put_contents(
                    sprintf("%s/%s.php", $outDir, $type->name),
                    $content
                );

                $this->writeVerbose($input, $output, '<info>Generated: </info>' . $type->name);
            }
        }

        return 0;
    }

    private function writeVerbose(InputInterface $input, OutputInterface $output, string $string): void
    {
        if ($input->getOption('verbose')) {
            $output->writeln($string);
        }
    }
}
