<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Adds the airline skin to the TYPO3 backend.
 */
final class BackendStyleHook
{
    public function registerSkin(): void
    {
        $GLOBALS['TBE_STYLES']['stylesheet'] = 'EXT:flight_ops/Resources/Public/Css/airline.css';
        $GLOBALS['TBE_STYLES']['stylesheet2'] = 'EXT:flight_ops/Resources/Public/Css/airline-print.css';
    }

    public function collectUpdateSignals(): string
    {
        return (string)BackendUtility::getUpdateSignalCode();
    }

    public function resolvePageType(TypoScriptFrontendController $frontendController): int
    {
        return (int)$frontendController->type;
    }

    public function buildBoardingPass(object $userAuthentication): string
    {
        // Weak match: the scanner cannot resolve the type of $userAuthentication.
        return (string)$userAuthentication->formfield_uname;
    }

    public function renderGateTree(object $contentObject, string $pageList): string
    {
        // Weak match on a deprecated method.
        return (string)$contentObject->getTreeList(1, 99, 0, $pageList);
    }
}
