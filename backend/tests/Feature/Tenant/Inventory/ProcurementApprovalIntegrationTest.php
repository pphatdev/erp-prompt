<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\ApprovalLevel;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use App\Tenants\Modules\Inventory\Services\ProcurementService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\TenantTestCase;

class ProcurementApprovalIntegrationTest extends TenantTestCase
{
    private ProcurementService $proc;
    private Warehouse $wh;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proc = app(ProcurementService::class);
        $this->wh = Warehouse::create(['code' => 'WH', 'name' => 'Main']);
        $this->supplier = Supplier::create([
            'name' => 'Acme Supplies',
            'email' => 'sales@acme.test',
        ]);
        $this->product = Product::create([
            'sku' => 'P-1', 'name' => 'Widget',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);

        Auth::login($this->admin);
    }

    private function makeDraftPO(): PurchaseOrder
    {
        return $this->proc->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->wh->id,
            'items' => [
                ['product_id' => $this->product->id, 'ordered_qty' => 5, 'unit_cost' => 8],
            ],
        ]);
    }

    public function test_submit_without_configured_workflow_keeps_legacy_behaviour(): void
    {
        $po = $this->makeDraftPO();
        $submitted = $this->proc->submit($po);

        // No workflow → PO sits in 'submitted', no ApprovalRequest filed.
        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $submitted->status);
        $this->assertSame(0, ApprovalRequest::where('requestable_id', $po->id)->count());
    }

    public function test_submit_with_workflow_files_approval_request(): void
    {
        $workflow = ApprovalWorkflow::create([
            'name'   => 'PO Approval',
            'module' => 'inventory',
            'type'   => 'purchase_order',
        ]);
        ApprovalLevel::create([
            'workflow_id'   => $workflow->id,
            'sequence'      => 1,
            'approver_role' => 'admin',
        ]);

        $po = $this->makeDraftPO();
        $this->proc->submit($po);

        $req = ApprovalRequest::where('requestable_id', $po->id)->first();
        $this->assertNotNull($req);
        $this->assertSame('pending', $req->status);
        $this->assertSame(PurchaseOrder::class, $req->requestable_type);
        $this->assertSame($workflow->id, $req->workflow_id);
    }

    public function test_approval_finalisation_flips_po_to_approved(): void
    {
        $workflow = ApprovalWorkflow::create([
            'name'   => 'PO Approval',
            'module' => 'inventory',
            'type'   => 'purchase_order',
        ]);
        ApprovalLevel::create([
            'workflow_id'   => $workflow->id,
            'sequence'      => 1,
            'approver_role' => 'admin',
        ]);

        $po = $this->makeDraftPO();
        $this->proc->submit($po);

        $req = ApprovalRequest::where('requestable_id', $po->id)->first();

        // Drive the approval through the engine — same path the
        // ApprovalActionController exercises in production.
        app(ApprovalService::class)
            ->processAction($req, $this->admin, 'approved', 'LGTM');

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->status);
        $this->assertSame($this->admin->id, $po->approved_by);
        $this->assertNotNull($po->approved_at);
    }

    public function test_approval_rejection_cancels_po(): void
    {
        $workflow = ApprovalWorkflow::create([
            'name'   => 'PO Approval',
            'module' => 'inventory',
            'type'   => 'purchase_order',
        ]);
        ApprovalLevel::create([
            'workflow_id'   => $workflow->id,
            'sequence'      => 1,
            'approver_role' => 'admin',
        ]);

        $po = $this->makeDraftPO();
        $this->proc->submit($po);

        $req = ApprovalRequest::where('requestable_id', $po->id)->first();
        app(ApprovalService::class)
            ->processAction($req, $this->admin, 'rejected', 'Wrong supplier');

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $po->status);
        $this->assertSame('Rejected via approval workflow', $po->cancel_reason);
    }
}
