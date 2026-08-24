<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Covers the TCA of the extension.
 *
 * WORKSHOP: this test does not assert anything about migrations - it does not
 * have to. Booting TYPO3 with outdated TCA raises E_USER_DEPRECATED, and the
 * shipped FunctionalTests.xml sets failOnDeprecation="true". The assertion
 * passes and the suite still goes red. See Lab 03.
 */
final class DepartureTcaTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'webvision/flight-ops',
    ];

    #[Test]
    public function departureTableIsRegistered(): void
    {
        self::assertArrayHasKey('tx_flightops_domain_model_departure', $GLOBALS['TCA']);
    }

    #[Test]
    public function departureTableExposesFlightNumber(): void
    {
        self::assertArrayHasKey(
            'flight_number',
            $GLOBALS['TCA']['tx_flightops_domain_model_departure']['columns']
        );
    }
}
