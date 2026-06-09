<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

final class VersionResolve extends Command
{
    public const NAME = 'version:resolve';

    private Parser $configParser;

    public function __construct()
    {
        $this->configParser = new Parser();
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Extracts and normalises the Shopify API version from the first source URL.')
            ->addOption(
                name: 'config',
                shortcut: 'c',
                mode: InputOption::VALUE_REQUIRED,
                default: './configuration.yaml',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        if (!is_string($configPath)) {
            throw new \RuntimeException('Option --config must be a string.');
        }

        $config = $this->parseConfig($configPath);

        $firstSource = reset($config['sources']);
        if (!is_array($firstSource)) {
            throw new \RuntimeException('No sources found in configuration.');
        }

        $url = is_string($firstSource['url'] ?? null) ? $firstSource['url'] : '';

        $version = $this->extractVersion($url);
        $output->write($version);

        return Command::SUCCESS;
    }

    /**
     * Extracts a YYYY-MM segment from a URL and normalises it to YYYY.MM.
     *
     * Examples:
     *   https://shopify.dev/admin-graphql-direct-proxy/2026-04  → 2026.04
     *   https://example.com/api/2024-10/graphql                 → 2024.10
     */
    public function extractVersion(string $url): string
    {
        if (!preg_match('/(\d{4})-(\d{2})/', $url, $matches)) {
            throw new \RuntimeException(
                "Could not extract a YYYY-MM version segment from URL: $url"
            );
        }

        return $matches[1] . '.' . $matches[2];
    }

    /**
     * @return array{sources: array<string, array<string, mixed>>}
     */
    private function parseConfig(string $path): array
    {
        $raw = $this->configParser->parseFile($path);
        if (!is_array($raw)) {
            throw new \RuntimeException("Configuration file '$path' must contain a YAML mapping.");
        }
        if (!isset($raw['sources']) || !is_array($raw['sources'])) {
            throw new \RuntimeException("Configuration file '$path' must have a 'sources' key.");
        }
        /** @var array{sources: array<string, array<string, mixed>>} $raw */
        return $raw;
    }
}
