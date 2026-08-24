<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Collects departure records for the flight board.
 */
final class BoardingService
{
    public function findDepartures(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_flightops_domain_model_departure');

        // WORKSHOP: ->execute() is deprecated in v12 and removed in v13.
        // The Extension Scanner has no rule for it. The functional test does.
        $rows = $queryBuilder
            ->select('*')
            ->from('tx_flightops_domain_model_departure')
            ->orderBy('uid', 'ASC')
            ->execute()
            ->fetchAllAssociative();

        return $rows;
    }

    public function resolveGateLink(ContentObjectRenderer $contentObject, int $pageId): string
    {
        $contentObject->typoLink('', ['parameter' => $pageId]);

        return $contentObject->lastTypoLinkUrl;
    }

    public function collectSubPages(PageRepository $pageRepository, int $rootPageId): array
    {
        $constraint = $pageRepository->where_hid_del;

        return $this->fetchTree($rootPageId, $constraint);
    }

    private function fetchTree(int $rootPageId, string $constraint): array
    {
        return [$rootPageId => $constraint];
    }
}
