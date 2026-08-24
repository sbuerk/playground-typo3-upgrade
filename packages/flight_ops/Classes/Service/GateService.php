<?php

declare(strict_types=1);

namespace Webvision\FlightOps\Service;

/**
 * Pure gate-number logic. No TYPO3 API, no database - the easy stuff to cover.
 */
final class GateService
{
    private const TERMINALS = ['A', 'B', 'C'];

    /**
     * "A12" -> ['terminal' => 'A', 'number' => 12]
     */
    public function parse(string $gate): array
    {
        $gate = strtoupper(trim($gate));
        if (!preg_match('/^([A-C])(\d{1,2})$/', $gate, $matches)) {
            throw new \InvalidArgumentException('Not a gate: ' . $gate, 1724500000);
        }

        return ['terminal' => $matches[1], 'number' => (int)$matches[2]];
    }

    public function isInternational(string $gate): bool
    {
        return $this->parse($gate)['terminal'] === 'C';
    }

    /**
     * @return list<string>
     */
    public function terminals(): array
    {
        return self::TERMINALS;
    }
}
