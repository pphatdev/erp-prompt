# eCommerce Module Architectural Flows

## 1. Full B2C Customer Journey

This diagram depicts the end-to-end checkout flow from browsing to inventory reservation, payment authorization, downstream sales order generation, and fulfillment:

```mermaid
graph TD
    Public[Public Storefront<br/>GET /api/v1/shop/products] --> CartAdd[Add to Cart<br/>POST /api/v1/shop/cart]
    
    subgraph Inventory Reservation
        CartAdd --> ReserveStock[InventoryService::reserve<br/>Creates 15-minute stock lock]
    end

    ReserveStock --> CheckoutInit[Initiate Checkout<br/>POST /api/v1/shop/checkout<br/>Generates client_uuid]
    
    subgraph Payment & Idempotency
        CheckoutInit --> PendingOrder[Create EcomOrder<br/>status=pending_payment]
        PendingOrder --> PaymentGateway[Stripe Checkout Element<br/>Returns payment intent]
    end

    PaymentGateway -- Success Payment --> ConfirmOrder[Confirm Order<br/>POST /api/v1/shop/checkout/confirm]
    
    subgraph Transaction Boundary
        ConfirmOrder --> CommitStock[Commit Stock Reserve<br/>StockMovement type=ecom_sale]
        CommitStock --> SalesShim[Create Sales Order Shim]
        SalesShim --> GenInvoice[InvoiceService::createFromOrder<br/>Posts AR balanced journal]
        GenInvoice --> PaidStatus[Set EcomOrder status=paid]
    end

    PaidStatus --> AdminFulfill[Admin Fulfillment<br/>pages/ecommerce/orders]
    AdminFulfill --> ShipStatus[FulfillmentService::ship<br/>Set status=shipped + Courier Link]
```

---

## 2. Refund Lifecycle Sequence (FMS & Inventory)

This diagram details the sequence triggered during an admin refund approval, demonstrating cash/bank reversals and inventory restocking:

```mermaid
sequenceDiagram
    actor Admin as Administrator
    participant RC as RefundController
    participant RS as RefundService
    participant PG as Payment Gateway
    participant DB as Tenant Database
    participant FMS as FMS / Accounting

    Admin->>RC: POST /api/v1/ecommerce/refunds/{id}/approve
    RC->>RS: approveRefund(refund)
    Note over RS: Initiate DB Transaction
    
    RS->>PG: Trigger Refund Charge API
    PG-->>RS: Gateway Confirmation Received
    
    RS->>DB: Log StockMovement (type=ecom_restock)
    RS->>FMS: AccountingService::postEntry (Reversing Journal)
    Note over FMS: Debit Revenue & Tax Payable<br/>Credit Cash/Bank Account
    
    RS->>DB: UPDATE ecom_orders SET status=refunded
    RS->>DB: UPDATE ecom_refunds SET status=completed
    
    Note over RS: Commit DB Transaction
    RS-->>RC: 200 RefundResource (completed)
    RC-->>Admin: Show Success Toast
```

---

## 3. Webhook Idempotency Check

This flow shows how the webhook gateway secures and processes callbacks safely without double-capturing or processing replays:

```mermaid
graph TD
    Webhook[Gateway Event Callback<br/>POST /api/v1/ecom/webhooks/stripe] --> VerifySig{Verify Signature?}
    VerifySig -- Invalid --> Refuse[400 Bad Request]
    VerifySig -- Valid --> CheckLogs{Event ID exists<br/>in ecom_payments?}
    
    CheckLogs -- Yes --> SuccessNoOp[200 OK<br/>Replay ignored]
    CheckLogs -- No --> LockMutex[Acquire Order Mutex]
    
    LockMutex --> IsPending{EcomOrder status<br/>== pending_payment?}
    IsPending -- No --> ReleaseNoOp[Release Mutex + 200 OK<br/>Order already settled]
    IsPending -- Yes --> ConfirmService[CheckoutService::confirm]
    ConfirmService --> ReleaseReturn[Release Mutex + 200 OK]
```

---

## 4. API Surface Routing Matrix

All endpoints must be declared in `routes/tenant.php` scoped under module comments:

| Method | Path | Action / Controller | Authentication | Role Guard |
|:---|:---|:---|:---|:---|
| **GET** | `/api/v1/shop/products` | `StorefrontController@index` | Public (Cached 60s) | - |
| **GET** | `/api/v1/shop/products/{slug}`| `StorefrontController@show` | Public | - |
| **POST**| `/api/v1/shop/cart` | `CartController@addItem` | Guest session OR Shopper | - |
| **POST**| `/api/v1/shop/cart/merge` | `CartController@mergeCart` | Shopper Authenticated | `shopper` |
| **POST**| `/api/v1/shop/checkout` | `CheckoutController@initiate`| Shopper Authenticated | `shopper` |
| **POST**| `/api/v1/shop/checkout/confirm`| `CheckoutController@confirm`| Shopper Authenticated | `shopper` |
| **POST**| `/api/v1/ecom/webhooks/{provider}`| `WebhookController@handle`| Signature Verified Only | - |
| **GET** | `/api/v1/ecommerce/orders` | `OrderController@index` | Central Admin Auth | `ecommerce.orders.read` |
| **PATCH**| `/api/v1/ecommerce/orders/{id}/fulfill`| `FulfillmentController@ship`| Central Admin Auth | `ecommerce.orders.write` |
| **POST**| `/api/v1/ecommerce/refunds` | `RefundController@request` | Central Admin Auth | `ecommerce.refunds.write` |
| **POST**| `/api/v1/ecommerce/refunds/{id}/approve`| `RefundController@approve`| Central Admin Auth | `ecommerce.refunds.approve` |

---

## 5. Backend Call Graph: Storefront checkout

```mermaid
sequenceDiagram
    participant Vue as Storefront UI (Nuxt)
    participant CC as CheckoutController
    participant CS as CheckoutService
    participant INV as Inventory Module (INV-RESERVE)
    participant SM as StockMovement Service
    participant SO as Sales Module (Order/Invoice)
    participant DB as DB Transaction

    Vue->>CC: POST /api/v1/shop/checkout/confirm { orderId, transactionId }
    CC->>CS: confirm(order, transactionId)
    
    CS->>DB: Start Transaction
    
    CS->>INV: Verify stock reservation is active
    CS->>SM: recordMovement(variant, qty, type=ecom_sale)
    CS->>SO: InvoiceService::createFromOrder(order)
    Note over SO: Debit Accounts Receivable (AR)<br/>Credit Sales Revenue
    
    CS->>DB: Update order status to 'paid'
    
    CS->>DB: Commit Transaction
    DB-->>CS: Transaction Success
    CS-->>CC: Return EcomOrderResource
    CC-->>Vue: 200 OK EcomOrder JSON
```
