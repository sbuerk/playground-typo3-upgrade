<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the flight board plugin through a real frontend request.
 *
 * WORKSHOP: this is the "cover your plugins" test. It exercises the whole chain
 * in one go - plugin registration, Extbase dispatch, the controller, the service,
 * the database and the Fluid template. Every link in that chain can break on a
 * major upgrade, and several of them break silently.
 */
final class FlightBoardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'webvision/flight-ops',
    ];

    protected array $coreExtensionsToLoad = [
        'fluid_styled_content',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/departures.csv');
        $this->writeSite('flight', 1, 'https://flight.example.com/');
        $this->setUpFrontendRootPage(1, [
            'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
            'EXT:flight_ops/Configuration/TypoScript/setup.typoscript',
        ]);
    }

    /**
     * testing-framework 8 has no site helper, so write the site configuration
     * into the test instance ourselves.
     */
    private function writeSite(string $identifier, int $rootPageId, string $base): void
    {
        $path = Environment::getConfigPath() . '/sites/' . $identifier;
        GeneralUtility::mkdir_deep($path);
        file_put_contents($path . '/config.yaml', implode("\n", [
            'rootPageId: ' . $rootPageId,
            'base: ' . $base,
            'languages:',
            '  -',
            '    title: English',
            '    enabled: true',
            '    languageId: 0',
            '    base: /',
            '    locale: en_US.UTF-8',
            '    flag: us',
            '',
        ]));
    }

    private function renderBoard(): string
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://flight.example.com/')
        );

        return (string)$response->getBody();
    }

    #[Test]
    public function boardRendersFlightNumbersFromTheDatabase(): void
    {
        $body = $this->renderBoard();

        self::assertStringContainsString('LH123', $body);
        self::assertStringContainsString('OS456', $body);
    }

    #[Test]
    public function boardRendersDestinations(): void
    {
        $body = $this->renderBoard();

        self::assertStringContainsString('Munich', $body);
        self::assertStringContainsString('Vienna', $body);
    }

    #[Test]
    public function boardUsesTheExtensionTemplate(): void
    {
        self::assertStringContainsString('flight-board', $this->renderBoard());
    }
}
