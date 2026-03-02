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
}
