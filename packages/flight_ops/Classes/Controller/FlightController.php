<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Controller;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Webvision\FlightOps\Service\BoardingService;

/**
 * Renders the flight board plugin.
 */
final class FlightController extends ActionController
{
    public function __construct(
        private readonly BoardingService $boardingService,
    ) {}

    public function listAction(): \Psr\Http\Message\ResponseInterface
    {
        $requestedGates = GeneralUtility::intExplode(',', $this->settings['gates'] ?? '', true, 25);

        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            LocalizationUtility::translate('flight.delayed', 'flight_ops', [], 'de'),
            'Flight delayed',
            FlashMessage::WARNING,
            true
        );

        $this->view->assignMultiple([
            'gates' => $requestedGates,
            'message' => $message,
            'departures' => $this->boardingService->findDepartures(),
            'signal' => BackendUtility::getUpdateSignalCode(),
            'installPath' => TYPO3_mainDir,
        ]);

        return $this->htmlResponse();
    }
}
