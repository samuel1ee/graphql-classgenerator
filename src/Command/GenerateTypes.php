<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Command;

use Aksonov\GraphqlGenerator\FileWriter;
use Aksonov\GraphqlGenerator\SchemaFetcher;
use Aksonov\GraphqlGenerator\SchemaParser;
use Aksonov\GraphqlGenerator\Types\PhpFieldType;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class GenerateTypes extends Command
{
    public const string NAME = 'generate:types';

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

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        if (!is_string($configPath)) {
            throw new RuntimeException('Option --config must be a string.');
        }

        $config = $this->parseConfig($configPath);

        $output->writeln('<info>Generating GraphQL types…</info>');

        $globalScalars = $config['scalars'] ?? [];
        $totalCount = 0;

        foreach ($config['sources'] as $apiType => $params) {
            $output->writeln("  Fetching schema for <comment>$apiType</comment>…");

            $url = is_string($params['url'] ?? null) ? $params['url'] : '';
            /** @var array<string, string> $headers */
            $headers = is_array($params['headers'] ?? null) ? $params['headers'] : [];

            $schema = $this->schemaFetcher->getSchema($url, $headers);
            $outputDir = $input->getArgument('output');
            if (!is_string($outputDir)) {
                throw new RuntimeException('Argument output must be a string.');
            }
            $outDir = rtrim($outputDir, '/') . "/$apiType";

            $this->fileWriter->emptyDir($outDir);

            /** @var array<string, string> $sourceScalars */
            $sourceScalars = is_array($params['scalars'] ?? null) ? $params['scalars'] : [];

            $scalarMap = array_merge(
                PhpFieldType::BUILTIN_SCALARS,
                $globalScalars,
                $sourceScalars,
            );

            $count = 0;
            foreach ($this->schemaParser->denormalizeSchema($schema) as $typeName => $type) {
                $namespace = is_string($params['namespace'] ?? null) ? $params['namespace'] : '';
                $content = $this->fileWriter->typeToClass($namespace, $type, $scalarMap);

                file_put_contents(
                    sprintf("%s/%s.php", $outDir, $type->name),
                    $content
                );

                $this->writeVerbose($input, $output, "    <info>Generated:</info> $typeName");
                $count++;
            }

            $output->writeln("  <info>✓</info> $apiType — $count types written to $outDir");
            $totalCount += $count;
        }

        $output->writeln("<info>Done.</info> $totalCount types generated in total.");

        return Command::SUCCESS;
    }

    private function writeVerbose(InputInterface $input, OutputInterface $output, string $string): void
    {
        if ($input->getOption('verbose')) {
            $output->writeln($string);
        }
    }

    /**
     * @return array{sources: array<string, array<string, mixed>>, scalars?: array<string, string>}
     */
    private function parseConfig(string $path): array
    {
        $raw = $this->configParser->parseFile($path);
        if (!is_array($raw)) {
            throw new RuntimeException("Configuration file '$path' must contain a YAML mapping.");
        }
        if (!isset($raw['sources']) || !is_array($raw['sources'])) {
            throw new RuntimeException("Configuration file '$path' must have a 'sources' key.");
        }
        /** @var array{sources: array<string, array<string, mixed>>, scalars?: array<string, string>} $raw */
        return $raw;
    }
}
