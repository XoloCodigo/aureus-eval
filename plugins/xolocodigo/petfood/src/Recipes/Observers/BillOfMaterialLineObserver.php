<?php

namespace XoloCodigo\PetFood\Recipes\Observers;

use Webkul\Manufacturing\Models\BillOfMaterialLine;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;

/**
 * Versions a recipe whenever its lines change. A recipe change is a change to
 * the BOM's lines (components/quantities), not its header, so we watch the
 * lines. The service groups all line changes of one edit into a single version.
 */
class BillOfMaterialLineObserver
{
    public function __construct(private BomVersionService $versions) {}

    public function saved(BillOfMaterialLine $line): void
    {
        $this->capture($line);
    }

    public function deleted(BillOfMaterialLine $line): void
    {
        $this->capture($line);
    }

    private function capture(BillOfMaterialLine $line): void
    {
        $bom = $line->billOfMaterial;

        if ($bom !== null) {
            $this->versions->snapshot($bom);
        }
    }
}
