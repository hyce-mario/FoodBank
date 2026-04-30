<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by DistributionPostingService::postForVisit() when an inventory item
 * does not have enough quantity_on_hand to fulfil the event distribution.
 *
 * Carries enough context for the controller to render a "skip / substitute /
 * cancel" modal in Phase 2.1.e: which item ran short, how many are needed,
 * how many are available, and which event triggered the check.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.e.
 */
class InsufficientStockException extends RuntimeException
{
    /**
     * @param  int  $eventId          The event during which the shortage was detected.
     * @param  int  $inventoryItemId  The item that ran short.
     * @param  int  $needed           How many units were required.
     * @param  int  $available        How many units were in stock.
     */
    public function __construct(
        public readonly int $eventId,
        public readonly int $inventoryItemId,
        public readonly int $needed,
        public readonly int $available,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Insufficient stock for item #{$inventoryItemId}: needed {$needed}, available {$available}."
        );
    }
}
