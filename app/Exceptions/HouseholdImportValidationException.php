<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase 6.5.e — refuses an import upload when the file is malformed or any
 * row fails validation. Carries the full row-level error report so the
 * upload page can render a table:
 *
 *   $e->errors == [
 *       ['row' => 'header', 'column' => null,         'message' => 'Missing required column "first_name"'],
 *       ['row' => 47,       'column' => 'phone',      'message' => 'Phone field corrupted by Excel auto-format (5.55E+9). Re-format as Text in your spreadsheet.'],
 *       ['row' => 48,       'column' => 'email',      'message' => 'Invalid email format ("not-an-email").'],
 *       …
 *   ]
 *
 * The "all-or-nothing" rule (chosen by the user during scoping) means a
 * single bad row aborts the whole upload. Admins fix the source file and
 * re-upload — no partial commits, no inconsistent state.
 */
class HouseholdImportValidationException extends RuntimeException
{
    /**
     * @param  array<int,array{row: int|string, column: ?string, message: string}>  $errors
     */
    public function __construct(
        public readonly array $errors,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf('Import file failed validation (%d error%s).', count($errors), count($errors) === 1 ? '' : 's')
        );
    }
}
