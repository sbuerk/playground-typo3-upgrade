<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['flight_ops']['enabled'] = true;

// WORKSHOP: registered the classic way, as a "list_type" plugin.
// Silent on 12.4. Deprecated on 13.4 - and only a test that actually renders
// the plugin will ever tell you.
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'FlightOps',
    'Board',
    [\Webvision\FlightOps\Controller\FlightController::class => 'list'],
    []
);
