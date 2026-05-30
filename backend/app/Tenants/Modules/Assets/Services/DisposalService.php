<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetDisposal;
use App\Tenants\Modules\FMS\Services\FmsIntegrationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DisposalService
{
    public const TYPE_SALE     = 'sale';
    public const TYPE_SCRAP    = 'scrap';
    public const TYPE_WRITEOFF = 'writeoff';

    public function __construct(private readonly FmsIntegrationService $fms)
    {
    }

    /**
     * Retire an asset. Computes final NBV, gain/loss vs sale price, posts a
     * balanced disposal journal, marks the asset retired, and soft-deletes.
     *
     * @param  array<string, mixed>  $extra  optional: notes
     */
    public function dispose(
        Asset $asset,
        string $disposalType,
        float $salePrice = 0.0,
        ?CarbonInterface $disposalDate = null,
        array $extra = [],
    ): AssetDisposal {
        $disposalType = strtolower($disposalType);
        if (!in_array($disposalType, [self::TYPE_SALE, self::TYPE_SCRAP, self::TYPE_WRITEOFF], true)) {
            throw new InvalidArgumentException("Unsupported disposal type: {$disposalType}");
        }

        if ($asset->status === 'retired') {
            throw new InvalidArgumentException("Asset {$asset->asset_code} is already retired.");
        }

        $date = $disposalDate ?? Carbon::now();
        $finalNbv = (float) $asset->net_book_value;

        // Sale: gain when sale > NBV; loss when sale < NBV.
        // Scrap/writeoff: entire NBV is a loss.
        if ($disposalType === self::TYPE_SALE) {
            $gainLoss = round($salePrice - $finalNbv, 2);
            $gainLossType = $gainLoss > 0 ? 'gain' : ($gainLoss < 0 ? 'loss' : 'none');
        } else {
            $salePrice = 0.0;
            $gainLoss = -$finalNbv;
            $gainLossType = $finalNbv > 0 ? 'loss' : 'none';
        }

        return DB::transaction(function () use ($asset, $disposalType, $date, $salePrice, $finalNbv, $gainLoss, $gainLossType, $extra) {
            $disposal = AssetDisposal::create([
                'asset_id'       => $asset->id,
                'disposal_date'  => $date->toDateString(),
                'disposal_type'  => $disposalType,
                'sale_price'     => $salePrice,
                'final_nbv'      => $finalNbv,
                'gain_loss'      => $gainLoss,
                'gain_loss_type' => $gainLossType,
                'notes'          => $extra['notes'] ?? null,
            ]);

            $journal = $this->fms->postDisposalJournal($asset, $disposal);
            $disposal->update(['journal_entry_id' => $journal->id]);

            $asset->update(['status' => 'retired']);
            $asset->delete();

            return $disposal->refresh();
        });
    }
}
