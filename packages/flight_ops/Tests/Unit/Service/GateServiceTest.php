<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webvision\FlightOps\Service\GateService;

final class GateServiceTest extends UnitTestCase
{
    private GateService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new GateService();
    }

    public static function gateProvider(): array
    {
        return [
            'terminal A' => ['A12', 'A', 12],
            'terminal B, single digit' => ['b3', 'B', 3],
            'terminal C' => ['C07', 'C', 7],
        ];
    }

    #[Test]
    #[DataProvider('gateProvider')]
    public function parseSplitsTerminalAndNumber(string $gate, string $terminal, int $number): void
    {
        self::assertSame(['terminal' => $terminal, 'number' => $number], $this->subject->parse($gate));
    }

    #[Test]
    public function parseRejectsUnknownTerminal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1724500000);
        $this->subject->parse('Z1');
    }

    #[Test]
    public function terminalCIsInternational(): void
    {
        self::assertTrue($this->subject->isInternational('C4'));
        self::assertFalse($this->subject->isInternational('A4'));
    }
}
