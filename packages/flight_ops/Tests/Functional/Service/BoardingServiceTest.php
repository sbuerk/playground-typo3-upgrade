<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webvision\FlightOps\Service\BoardingService;

/**
 * Covers the service that talks to the database.
 *
 * WORKSHOP: this is the test that turns a removed core API into a red build
 * instead of a fatal error in production.
 */
final class BoardingServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'webvision/flight-ops',
    ];

    private BoardingService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/departures.csv');
        $this->subject = new BoardingService();
    }

    #[Test]
    public function findDeparturesReturnsAllRows(): void
    {
        $rows = $this->subject->findDepartures();

        self::assertCount(2, $rows);
    }

    #[Test]
    public function findDeparturesReturnsFlightNumbers(): void
    {
        $flightNumbers = array_column($this->subject->findDepartures(), 'flight_number');

        self::assertSame(['LH123', 'OS456'], $flightNumbers);
    }

    #[Test]
    public function findDeparturesReturnsDestinations(): void
    {
        $rows = $this->subject->findDepartures();

        self::assertSame('Munich', $rows[0]['destination']);
        self::assertSame('Vienna', $rows[1]['destination']);
    }
}
