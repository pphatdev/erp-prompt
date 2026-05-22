# Sales Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `sales`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `crm` | `sales.crm.read` | `sales.crm.write` | `sales.crm.delete` | `sales.crm.export` |
| `orders` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |
| `invoices` | `sales.invoices.read` | `sales.invoices.write` | `sales.invoices.delete` | `sales.invoices.export` |
| `leads` | `sales.leads.read` | `sales.leads.write` | `sales.leads.delete` | `sales.leads.export` |

## 2. Implementation Standards

### Hybrid Order-to-Cash (O2C) Workflow
1. **Create Customer**: Register the new client/lead.
2. **Create Quote**: 
   - Add Products (Can be a mix of **Software** modules and **Hardware** like Laptops/Phones).
   - Select Product Variant (e.g., Monthly/Yearly for Software, or Color/Storage for Hardware).
   - Set Quantity, Unit Price, Total Price, Due Date.
   - Set Status (`New`, `Confirmed`, `Cancelled`).
3. **Create Sales Order**: 
   - Convert from Quote.
   - Set Status (`New`, `Confirmed`, `Cancelled`).
4. **Create Invoice**:
   - Convert from Sales Order.
   - Set Status (`New`, `Confirmed`, `Cancelled`).
5. **Setup Subscription** (For Software/SaaS Products):
   - Convert from Sales Order.
   - Set Status (`New`, `Confirmed`, `Cancelled`).
   - *Note for Hardware*: If the Sales Order includes Hardware, trigger inventory deduction and shipping fulfillment simultaneously.
6. **Customer Access** (Tenant Provisioning):
   - Create customer account.
   - Customer can login to the system.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Sales`
- **Service Layer**: Logic in `Services/OrderService.php`, `Services/SubscriptionService.php`, and `Services/InventoryService.php`.
- **Transactions**: Order creation, inventory deduction, and subscription provisioning MUST be atomic.
- **Resources**: Use `OrderResource`, `SubscriptionResource`, and `CustomerResource` for API responses.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/sales/`
- **Components**: Use PrimeVue DataTables for lead and order management.
- **UX**: Implement real-time notifications for order status updates.
