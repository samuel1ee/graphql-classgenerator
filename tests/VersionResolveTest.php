<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\Command\VersionResolve;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class VersionResolveTest extends TestCase
{
    private VersionResolve $command;

    protected function setUp(): void
    {
        $this->command = new VersionResolve();
    }

    // ── extractVersion (unit) ────────────────────────────────────────────────

    public function testExtractsVersionFromTrailingSegment(): void
    {
        $this->assertSame(
            '2026.04',
            $this->command->extractVersion('https://shopify.dev/admin-graphql-direct-proxy/2026-04')
        );
    }

    public function testExtractsVersionFromMidpathSegment(): void
    {
        $this->assertSame(
            '2024.10',
            $this->command->extractVersion('https://example.com/api/2024-10/graphql.json')
        );
    }

    public function testExtractsFirstOccurrenceWhenMultiplePresent(): void
    {
        $this->assertSame(
            '2024.07',
            $this->command->extractVersion('https://example.com/api/2024-07/subpath/2025-01')
        );
    }

    public function testThrowsWhenNoVersionInUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not extract a YYYY-MM version segment');

        $this->command->extractVersion('https://example.com/graphql');
    }

    // ── Command integration (via temp config file) ───────────────────────────

    public function testCommandOutputsNormalisedVersion(): void
    {
        $yaml = <<<'YAML'
sources:
  AdminApi:
    namespace: Shopify\Types\AdminApi
    url: https://shopify.dev/admin-graphql-direct-proxy/2026-04
YAML;

        $configFile = tempnam(sys_get_temp_dir(), 'vr_') . '.yaml';
        file_put_contents($configFile, $yaml);

        try {
            $tester = new CommandTester($this->command);
            $tester->execute(['--config' => $configFile]);

            $this->assertSame(0, $tester->getStatusCode());
            $this->assertSame('2026.04', $tester->getDisplay());
        } finally {
            unlink($configFile);
        }
    }

    public function testCommandFailsWithInvalidConfig(): void
    {
        $this->expectException(\RuntimeException::class);

        $tester = new CommandTester($this->command);
        $tester->execute(['--config' => '/nonexistent/path/config.yaml']);
    }
}
