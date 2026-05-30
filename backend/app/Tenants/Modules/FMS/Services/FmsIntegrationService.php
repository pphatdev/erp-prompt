<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetDisposal;
use App\Models\Tenant\AssetRevaluationLog;
use App\Models\Tenant\JournalEntry;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Domain-specific boundary the Assets module uses to talk to FMS. The methods
 * resolve the right GL accounts (configurable per tenant via settings) and
 * delegate to {@see AccountingService::postEntry} to actually persist the
 * balanced double-entry record. Mocked in tests — never call AccountingService
 * directly from inside Assets services.
 */
class FmsIntegrationService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SettingService $settings,
    ) {
    }

    public function postDepreciationJournal(Asset $asset, float $amount, CarbonInterface $postingDate): JournalEntry
    {
        $expense     = $this->resolveAccount('assets.depreciation_expense_account_code', '5400');
        $accumulated = $this->resolveAccount('assets.accumulated_depreciation_account_code', '1500');

        return $this->accounting->postEntry([
            'reference_number' => sprintf('DEPR-%s-%s', $asset->asset_code, $postingDate->format('Ym')),
            'description'      => "Monthly depreciation for asset {$asset->asset_code} ({$asset->name})",
            'entry_date'       => $postingDate,
            'lines'            => [
                ['account_id' => $expense->id,     'debit'  => $amount, 'credit' => 0],
                ['account_id' => $accumulated->id, 'debit'  => 0,       'credit' => $amount],
            ],
        ]);
    }

    /**
     * Surplus revaluation: Debit asset value, Credit revaluation reserve.
     * Loss revaluation: Debit revaluation loss, Credit asset value.
     */
    public function postRevaluationJournal(Asset $asset, AssetRevaluationLog $log): JournalEntry
    {
        $assetAcct   = $this->resolveAccount('assets.cost_account_code', '1700');
        $reserveAcct = $this->resolveAccount('assets.revaluation_reserve_account_code', '3200');
        $lossAcct    = $this->resolveAccount('assets.revaluation_loss_account_code', '5500');

        $magnitude = abs((float) $log->adjustment_amount);

        $lines = $log->adjustment_type === 'surplus'
            ? [
                ['account_id' => $assetAcct->id,   'debit' => $magnitude, 'credit' => 0],
                ['account_id' => $reserveAcct->id, 'debit' => 0,           'credit' => $magnitude],
            ]
            : [
                ['account_id' => $lossAcct->id,  'debit' => $magnitude, 'credit' => 0],
                ['account_id' => $assetAcct->id, 'debit' => 0,           'credit' => $magnitude],
            ];

        return $this->accounting->postEntry([
            'reference_number' => sprintf('REVAL-%s-%s', $asset->asset_code, $log->appraisal_date->format('Ymd')),
            'description'      => "Revaluation ({$log->adjustment_type}) for asset {$asset->asset_code}",
            'entry_date'       => $log->appraisal_date,
            'lines'            => $lines,
        ]);
    }

    /**
     * Sale: Debit Cash (sale price) + Accumulated Depreciation, Credit Asset Cost,
     *       balanced by Gain or Loss on Disposal.
     * Scrap/Writeoff: Debit Accumulated Depreciation + Loss on Disposal (NBV),
     *                 Credit Asset Cost.
     */
    public function postDisposalJournal(Asset $asset, AssetDisposal $disposal): JournalEntry
    {
        $assetAcct       = $this->resolveAccount('assets.cost_account_code', '1700');
        $accumulatedAcct = $this->resolveAccount('assets.accumulated_depreciation_account_code', '1500');
        $gainLossAcct    = $this->resolveAccount('assets.disposal_gain_loss_account_code', '4300');
        $cashAcct        = $this->resolveAccount('assets.cash_account_code', '1000');

        $purchasePrice = (float) $asset->purchase_price;
        $accumulated   = (float) $asset->accumulated_depreciation;
        $salePrice     = (float) $disposal->sale_price;
        $gainLoss      = (float) $disposal->gain_loss;

        $lines = [];

        if ($disposal->disposal_type === 'sale') {
            $lines[] = ['account_id' => $cashAcct->id,        'debit' => $salePrice,   'credit' => 0];
            $lines[] = ['account_id' => $accumulatedAcct->id, 'debit' => $accumulated, 'credit' => 0];
            $lines[] = ['account_id' => $assetAcct->id,       'debit' => 0,             'credit' => $purchasePrice];

            if ($disposal->gain_loss_type === 'gain') {
                $lines[] = ['account_id' => $gainLossAcct->id, 'debit' => 0,                  'credit' => abs($gainLoss)];
            } elseif ($disposal->gain_loss_type === 'loss') {
                $lines[] = ['account_id' => $gainLossAcct->id, 'debit' => abs($gainLoss),     'credit' => 0];
            }
        } else {
            // scrap / writeoff — the entire remaining NBV is a loss.
            $lines[] = ['account_id' => $accumulatedAcct->id, 'debit' => $accumulated,        'credit' => 0];
            $lines[] = ['account_id' => $gainLossAcct->id,    'debit' => abs($gainLoss),      'credit' => 0];
            $lines[] = ['account_id' => $assetAcct->id,       'debit' => 0,                    'credit' => $purchasePrice];
        }

        return $this->accounting->postEntry([
            'reference_number' => sprintf('DISP-%s-%s', $asset->asset_code, $disposal->disposal_date->format('Ymd')),
            'description'      => "Disposal ({$disposal->disposal_type}) of asset {$asset->asset_code}",
            'entry_date'       => $disposal->disposal_date,
            'lines'            => $lines,
        ]);
    }

    private function resolveAccount(string $settingKey, string $fallbackCode): Account
    {
        $code = (string) ($this->settings->get($settingKey) ?? $fallbackCode);

        $account = Account::query()->where('code', $code)->first();
        if (!$account) {
            throw new RuntimeException(
                "FMS account not configured for {$settingKey}: looked up code '{$code}' — seed the Chart of Accounts or set the override in tenant settings."
            );
        }

        return $account;
    }
}
