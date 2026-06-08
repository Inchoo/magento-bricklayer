<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Command;

use Inchoo\MagentoBricklayer\Command\VerifyCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class VerifyCommandTest extends TestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $command = new VerifyCommand();
        $this->tester = new CommandTester($command);
    }

    public function testJsonOutputIsValidJsonWithCorrectStructure(): void
    {
        $this->tester->execute(['--json' => true]);

        $output = $this->tester->getDisplay();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'Output must be valid JSON');
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertIsArray($data['results']);

        foreach ($data['results'] as $result) {
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('message', $result);
            $this->assertIsString($result['name']);
            $this->assertIsString($result['status']);
            $this->assertMatchesRegularExpression('/^(pass|warn|fail)$/', $result['status']);
            $this->assertIsString($result['message']);
        }
    }

    public function testJsonSummaryCountsAreConsistent(): void
    {
        $this->tester->execute(['--json' => true]);

        $data = json_decode($this->tester->getDisplay(), true);
        $summary = $data['summary'];

        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('passed', $summary);
        $this->assertArrayHasKey('warnings', $summary);
        $this->assertArrayHasKey('failed', $summary);

        $this->assertIsInt($summary['total']);
        $this->assertIsInt($summary['passed']);
        $this->assertIsInt($summary['warnings']);
        $this->assertIsInt($summary['failed']);

        $this->assertSame(
            $summary['total'],
            $summary['passed'] + $summary['warnings'] + $summary['failed'],
            'total must equal passed + warnings + failed'
        );
    }

    public function testDefaultOutputContainsExpectedKeywords(): void
    {
        $this->tester->execute([]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('pass', $output);
        $this->assertStringContainsString('Magento', $output);
        $this->assertStringContainsString('Result', $output);
    }

    public function testItLoadsTheProjectConfigWithTheResolvedRootBeforeCheckingCodeRunner(): void
    {
        $tempDir = sys_get_temp_dir() . '/verify_cmd_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // Write a .bricklayer.json that disables code-runner
            file_put_contents(
                $tempDir . '/.bricklayer.json',
                (string) json_encode(['tools' => ['code-runner' => ['enabled' => false]]])
            );

            $command = new VerifyCommand();
            $tester = new CommandTester($command);
            $tester->execute(['--json' => true, '--magento-root' => $tempDir]);

            $data = json_decode($tester->getDisplay(), true);
            $this->assertNotNull($data);

            $codeRunnerResult = null;
            foreach ($data['results'] as $result) {
                if ($result['name'] === 'Code runner') {
                    $codeRunnerResult = $result;
                    break;
                }
            }

            $this->assertNotNull($codeRunnerResult, 'Code runner check must be present in results');
            // The project config sets code-runner disabled — the command must reflect that,
            // not fall back to built-in defaults ("enabled (default config)").
            $this->assertSame('warn', $codeRunnerResult['status']);
            $this->assertStringContainsString('disabled', $codeRunnerResult['message']);
        } finally {
            @unlink($tempDir . '/.bricklayer.json');
            @rmdir($tempDir);
        }
    }

    public function testItReflectsAProjectConfigOverrideOfCodeRunnerEnabledState(): void
    {
        $tempDir = sys_get_temp_dir() . '/verify_cmd_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // Write a .bricklayer.json that enables code-runner with allow_write
            file_put_contents(
                $tempDir . '/.bricklayer.json',
                (string) json_encode(['tools' => ['code-runner' => ['enabled' => true, 'allow_write' => true]]])
            );

            $command = new VerifyCommand();
            $tester = new CommandTester($command);
            $tester->execute(['--json' => true, '--magento-root' => $tempDir]);

            $data = json_decode($tester->getDisplay(), true);
            $this->assertNotNull($data);

            $codeRunnerResult = null;
            foreach ($data['results'] as $result) {
                if ($result['name'] === 'Code runner') {
                    $codeRunnerResult = $result;
                    break;
                }
            }

            $this->assertNotNull($codeRunnerResult, 'Code runner check must be present in results');
            $this->assertSame('pass', $codeRunnerResult['status']);
            $this->assertStringContainsString('read-write', $codeRunnerResult['message']);
        } finally {
            @unlink($tempDir . '/.bricklayer.json');
            @rmdir($tempDir);
        }
    }

    public function testItRendersAnUnknownStatusWithoutThrowing(): void
    {
        // Pre-seed $results with an unknown status via reflection;
        // execute() appends to (never resets) the property, so the entry survives
        // into the render loop and exercises the match default arm.
        $command = new VerifyCommand();
        $prop = new \ReflectionProperty(VerifyCommand::class, 'results');
        $prop->setValue($command, [
            ['name' => 'Future check', 'status' => 'unknown', 'message' => 'x'],
        ]);

        $tester = new CommandTester($command);

        // Must not throw UnhandledMatchError
        $this->expectNotToPerformAssertions();
        $tester->execute([]);
    }

    public function testItStillRendersPassWarnAndFailStatusesCorrectly(): void
    {
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // The standard statuses must render; we check the non-JSON text path renders
        // the summary line which only appears when all three statuses are handled.
        $this->assertStringContainsString('Result:', $output);
    }
}
