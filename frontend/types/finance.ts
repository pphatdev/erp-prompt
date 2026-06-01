import type { PaginatedResponse } from '~/types/sales'

export type { PaginatedResponse }

export interface ExchangeRate {
    id: string
    baseCurrency: string
    quoteCurrency: string
    pair: string
    rate: number
    effectiveDate: string | null
    source: string
    notes: string | null
    isActive: boolean
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateExchangeRatePayload {
    base_currency: string
    quote_currency: string
    rate: number
    effective_date: string
    source?: string | null
    notes?: string | null
    is_active?: boolean
}

export interface ConvertResult {
    amount: number
    from: string
    to: string
    rate: number
    converted: number
    effectiveDate: string | null
    rateId: string | null
    inverse: boolean
}

export type AccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'

export interface Account {
    id: string
    code: string
    name: string
    type: AccountType
    parentId: string | null
    balance: number
    aggregatedBalance: number
    childrenCount: number
    children: Account[]
    createdAt?: string | null
    updatedAt?: string | null
}

export interface CreateAccountPayload {
    code: string
    name: string
    type: AccountType
    parent_id?: string | null
}

export type JournalStatus = 'draft' | 'posted' | 'reversed'

export interface JournalLineAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface JournalLine {
    id: string
    account: JournalLineAccountSnapshot | null
    debit: number
    credit: number
}

export interface JournalEntry {
    id: string
    reference_number: string
    description: string | null
    entry_date: string
    status: JournalStatus
    reverses_journal_id: string | null
    reversed_by_journal_id: string | null
    lines: JournalLine[]
    created_at?: string | null
}

export interface ReverseJournalPayload {
    reference_number?: string | null
    description?: string | null
}

export interface BankAccountGlSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
    balance: number
}

export interface BankAccount {
    id: string
    name: string
    bankName: string
    branch: string | null
    accountNumber: string | null
    accountHolder: string | null
    swift: string | null
    iban: string | null
    currency: string
    openingBalance: number
    lastReconciledAt: string | null
    lastReconciledBalance: number | null
    notes: string | null
    isActive: boolean
    isDefault: boolean
    accountId: string | null
    glAccount?: BankAccountGlSnapshot | null
    bookBalance: number
    createdAt: string | null
    updatedAt: string | null
}

export type BillStatus = 'draft' | 'approved' | 'partially_paid' | 'paid' | 'cancelled'

export interface BillSupplierSnapshot {
    id: string
    code: string | null
    name: string
}

export interface BillAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface BillLine {
    id: string
    accountId: string
    account?: BillAccountSnapshot | null
    description: string | null
    quantity: number
    unitPrice: number
    lineTotal: number
}

export interface Bill {
    id: string
    billNumber: string
    supplierInvoiceNumber: string | null
    supplierId: string
    supplier?: BillSupplierSnapshot | null
    poId: string | null
    issueDate: string
    dueDate: string | null
    currency: string
    subtotal: number
    taxAmount: number
    total: number
    paidAmount: number
    outstandingAmount: number
    status: BillStatus
    isEditable: boolean
    isPostable: boolean
    isReversible: boolean
    payableAccountId: string | null
    payableAccount?: BillAccountSnapshot | null
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    lines: BillLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateBillLinePayload {
    account_id: string
    description?: string | null
    quantity: number
    unit_price: number
}

export interface CreateBillPayload {
    bill_number: string
    supplier_invoice_number?: string | null
    supplier_id: string
    po_id?: string | null
    issue_date: string
    due_date?: string | null
    currency?: string
    tax_amount?: number
    payable_account_id?: string | null
    notes?: string | null
    lines: CreateBillLinePayload[]
}

// ----- Pay Bill ---------------------------------------------------------------

export type BillPaymentStatus = 'posted' | 'cancelled'

export interface BillPaymentSupplierSnapshot {
    id: string
    code: string | null
    name: string
}

export interface BillPaymentBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface BillPaymentBillSnapshot {
    id: string
    billNumber: string
    total: number
    paidAmount: number
    outstandingAmount: number
    status: BillStatus
}

export interface BillPaymentApplication {
    id: string
    billId: string
    bill?: BillPaymentBillSnapshot | null
    appliedAmount: number
}

export interface BillPayment {
    id: string
    paymentNumber: string
    bankAccountId: string
    bankAccount?: BillPaymentBankSnapshot | null
    supplierId: string
    supplier?: BillPaymentSupplierSnapshot | null
    paidOn: string
    amount: number
    currency: string
    paymentMethod: string | null
    referenceNumber: string | null
    status: BillPaymentStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    applications: BillPaymentApplication[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateBillPaymentApplicationPayload {
    bill_id: string
    applied_amount: number
}

export interface CreateBillPaymentPayload {
    payment_number: string
    bank_account_id: string
    supplier_id: string
    paid_on: string
    amount: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    applications: CreateBillPaymentApplicationPayload[]
}

// ----- Reimbursement ----------------------------------------------------------

export type ReimbursementStatus = 'posted' | 'cancelled'

export interface ReimbursementEmployeeSnapshot {
    id: string
    employeeId: string | null
    fullName: string | null
}

export interface ReimbursementBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface ReimbursementLine {
    id: string
    accountId: string
    account?: BillAccountSnapshot | null
    description: string | null
    amount: number
    receiptAttachment: string | null
}

export interface Reimbursement {
    id: string
    reimbursementNumber: string
    employeeId: string
    employee?: ReimbursementEmployeeSnapshot | null
    bankAccountId: string
    bankAccount?: ReimbursementBankSnapshot | null
    paidOn: string
    amount: number
    currency: string
    paymentMethod: string | null
    referenceNumber: string | null
    status: ReimbursementStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    lines: ReimbursementLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateReimbursementLinePayload {
    account_id: string
    description?: string | null
    amount: number
    receipt_attachment?: string | null
}

export interface CreateReimbursementPayload {
    reimbursement_number: string
    employee_id: string
    bank_account_id: string
    paid_on: string
    amount: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    lines: CreateReimbursementLinePayload[]
}

// ----- Cash Advance -----------------------------------------------------------

export type CashAdvanceStatus = 'open' | 'partially_settled' | 'closed' | 'cancelled'

export interface CashAdvanceEmployeeSnapshot {
    id: string
    employeeId: string | null
    fullName: string | null
}

export interface CashAdvanceBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface CashAdvanceAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface CashAdvance {
    id: string
    advanceNumber: string
    employeeId: string
    employee?: CashAdvanceEmployeeSnapshot | null
    bankAccountId: string
    bankAccount?: CashAdvanceBankSnapshot | null
    receivableAccountId: string
    receivableAccount?: CashAdvanceAccountSnapshot | null
    issuedOn: string
    amount: number
    settledAmount: number
    outstandingAmount: number
    currency: string
    paymentMethod: string | null
    referenceNumber: string | null
    purpose: string | null
    status: CashAdvanceStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateCashAdvancePayload {
    advance_number: string
    employee_id: string
    bank_account_id: string
    receivable_account_id: string
    issued_on: string
    amount: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    purpose?: string | null
    notes?: string | null
}

// ----- Cash Advance Settlement ------------------------------------------------

export type CashAdvanceSettlementStatus = 'posted' | 'cancelled'

export interface CashAdvanceSettlementAdvanceSnapshot {
    id: string
    advanceNumber: string
    employee: CashAdvanceEmployeeSnapshot | null
    receivableAccount: { id: string; code: string; name: string } | null
    amount: number | null
    settledAmount: number | null
    outstandingAmount: number | null
    status: CashAdvanceStatus | null
    currency: string | null
}

export interface CashAdvanceSettlementBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface CashAdvanceSettlementLine {
    id: string
    accountId: string
    account?: BillAccountSnapshot | null
    description: string | null
    amount: number
    receiptAttachment: string | null
}

export interface CashAdvanceSettlement {
    id: string
    settlementNumber: string
    cashAdvanceId: string
    cashAdvance?: CashAdvanceSettlementAdvanceSnapshot | null
    bankAccountId: string | null
    bankAccount?: CashAdvanceSettlementBankSnapshot | null
    settledOn: string
    actualAmount: number
    unusedReturned: number
    appliedToAdvance: number
    paymentMethod: string | null
    referenceNumber: string | null
    status: CashAdvanceSettlementStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    lines: CashAdvanceSettlementLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateCashAdvanceSettlementLinePayload {
    account_id: string
    description?: string | null
    amount: number
    receipt_attachment?: string | null
}

export interface CreateCashAdvanceSettlementPayload {
    settlement_number: string
    cash_advance_id: string
    bank_account_id?: string | null
    settled_on: string
    actual_amount: number
    unused_returned?: number | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    lines: CreateCashAdvanceSettlementLinePayload[]
}

// ----- Expense ----------------------------------------------------------------

export type ExpenseStatus = 'posted' | 'cancelled'

export interface ExpenseBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface ExpenseSupplierSnapshot {
    id: string
    name: string
}

export interface ExpenseLine {
    id: string
    accountId: string
    account?: BillAccountSnapshot | null
    description: string | null
    amount: number
    receiptAttachment: string | null
}

export interface Expense {
    id: string
    expenseNumber: string
    bankAccountId: string
    bankAccount?: ExpenseBankSnapshot | null
    supplierId: string | null
    supplier?: ExpenseSupplierSnapshot | null
    paidOn: string
    total: number
    currency: string
    paymentMethod: string | null
    referenceNumber: string | null
    status: ExpenseStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    lines: ExpenseLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateExpenseLinePayload {
    account_id: string
    description?: string | null
    amount: number
    receipt_attachment?: string | null
}

export interface CreateExpensePayload {
    expense_number: string
    bank_account_id: string
    supplier_id?: string | null
    paid_on: string
    total: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    lines: CreateExpenseLinePayload[]
}

// ----- Receipt (AR) -----------------------------------------------------------

export type ReceiptStatus = 'posted' | 'cancelled'

export interface ReceiptCustomerSnapshot {
    id: string
    name: string
}

export interface ReceiptBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
}

export interface ReceiptArAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

/** Open invoice surfaced by `GET /receipts/open-invoices/{customer}`. */
export interface ReceiptOpenInvoice {
    id: string
    invoiceNumber: string
    status: 'confirmed'
    invoiceDate: string | null
    dueDate: string | null
    totalAmount: number
    paidAmount: number
    outstandingAmount: number
}

export interface ReceiptApplicationInvoiceSnapshot {
    id: string
    invoiceNumber: string
    status: string
    invoiceDate: string | null
    dueDate: string | null
    totalAmount: number | null
    paidAmount: number | null
    outstandingAmount: number | null
}

export interface ReceiptApplication {
    id: string
    invoiceId: string
    invoice?: ReceiptApplicationInvoiceSnapshot | null
    appliedAmount: number
}

export interface Receipt {
    id: string
    receiptNumber: string
    customerId: string
    customer?: ReceiptCustomerSnapshot | null
    bankAccountId: string
    bankAccount?: ReceiptBankSnapshot | null
    arAccountId: string
    arAccount?: ReceiptArAccountSnapshot | null
    receivedOn: string
    amount: number
    currency: string
    paymentMethod: string | null
    referenceNumber: string | null
    status: ReceiptStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    applications: ReceiptApplication[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateReceiptApplicationPayload {
    invoice_id: string
    applied_amount: number
}

export interface CreateReceiptPayload {
    receipt_number: string
    customer_id: string
    bank_account_id: string
    ar_account_id: string
    received_on: string
    amount: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    applications: CreateReceiptApplicationPayload[]
}

// ----- Credit Note (AR) -------------------------------------------------------

export type CreditNoteStatus = 'issued' | 'cancelled'

export interface CreditNoteAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface CreditNoteInvoiceSnapshot {
    id: string
    invoiceNumber: string
    status: string
    invoiceDate: string | null
    dueDate: string | null
    totalAmount: number
    paidAmount: number
    outstandingAmount: number
}

export interface CreditNote {
    id: string
    creditNoteNumber: string
    customerId: string
    customer?: ReceiptCustomerSnapshot | null
    invoiceId: string | null
    invoice?: CreditNoteInvoiceSnapshot | null
    salesReturnsAccountId: string
    salesReturnsAccount?: CreditNoteAccountSnapshot | null
    arAccountId: string
    arAccount?: CreditNoteAccountSnapshot | null
    issueDate: string
    amount: number
    currency: string
    reason: string
    status: CreditNoteStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateCreditNotePayload {
    credit_note_number: string
    customer_id: string
    invoice_id?: string | null
    sales_returns_account_id: string
    ar_account_id: string
    issue_date: string
    amount: number
    currency?: string | null
    reason: string
    notes?: string | null
}

// ----- Debit Note (AR, opposite of Credit Note) -------------------------------

export type DebitNoteStatus = 'issued' | 'cancelled'

export interface DebitNote {
    id: string
    debitNoteNumber: string
    customerId: string
    customer?: ReceiptCustomerSnapshot | null
    invoiceId: string | null
    invoice?: CreditNoteInvoiceSnapshot | null
    revenueAccountId: string
    revenueAccount?: CreditNoteAccountSnapshot | null
    arAccountId: string
    arAccount?: CreditNoteAccountSnapshot | null
    issueDate: string
    amount: number
    currency: string
    reason: string
    status: DebitNoteStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateDebitNotePayload {
    debit_note_number: string
    customer_id: string
    invoice_id?: string | null
    revenue_account_id: string
    ar_account_id: string
    issue_date: string
    amount: number
    currency?: string | null
    reason: string
    notes?: string | null
}

// ----- Bank Reconciliation ----------------------------------------------------

export type BankReconStatus = 'open' | 'closed'
export type StatementLineDirection = 'deposit' | 'withdrawal' | 'zero'

export interface BankReconMatchedLedgerEntrySnapshot {
    id: string
    journalEntryId: string
    debit: number
    credit: number
}

export interface BankReconStatementLine {
    id: string
    sessionId: string
    statementDate: string | null
    description: string
    referenceNumber: string | null
    amount: number
    direction: StatementLineDirection
    matchedLedgerEntryId: string | null
    matchedLedgerEntry?: BankReconMatchedLedgerEntrySnapshot | null
    isMatched: boolean
    notes: string | null
    createdAt: string | null
}

export interface BankReconSessionBankSnapshot {
    id: string
    name: string
    bankName: string
    currency: string
    glAccount: { id: string; code: string; name: string; balance: number } | null
}

export interface BankReconSession {
    id: string
    sessionNumber: string
    bankAccountId: string
    bankAccount?: BankReconSessionBankSnapshot | null
    startDate: string | null
    endDate: string | null
    openingBalance: number
    statementEndingBalance: number
    bookEndingBalance: number
    statementLinesTotal: number
    expectedEndingBalance: number
    balanceMatches: boolean
    unmatchedLinesCount: number
    status: BankReconStatus
    isClosable: boolean
    closedAt: string | null
    closedBy: string | null
    notes: string | null
    statementLines: BankReconStatementLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateBankReconSessionPayload {
    session_number: string
    bank_account_id: string
    start_date: string
    end_date: string
    opening_balance?: number | null
    statement_ending_balance: number
    notes?: string | null
}

export interface CreateBankReconStatementLinePayload {
    statement_date: string
    description: string
    reference_number?: string | null
    amount: number
    notes?: string | null
}

export interface BankReconPeriodLedgerEntry {
    id: string
    journalEntryId: string
    referenceNumber: string | null
    description: string | null
    entryDate: string | null
    debit: number
    credit: number
    direction: StatementLineDirection
    amountAbs: number
    matchedInSession: string | null
}

// ----- Budget -----------------------------------------------------------------

export type BudgetStatus = 'draft' | 'active' | 'archived'
export type VarianceBucket = 'green' | 'yellow' | 'red'

export interface BudgetLineVariance {
    expected: number
    actual: number
    variance: number
    variancePct: number | null
    bucket: VarianceBucket
}

export interface BudgetLineAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface BudgetLine {
    id: string
    budgetId: string
    accountId: string
    account?: BudgetLineAccountSnapshot | null
    expectedAmount: number
    notes: string | null
    variance?: BudgetLineVariance | null
}

export interface Budget {
    id: string
    budgetNumber: string
    name: string
    startDate: string | null
    endDate: string | null
    status: BudgetStatus
    isEditable: boolean
    isActivatable: boolean
    isArchivable: boolean
    expectedTotal: number
    linesCount: number | null
    notes: string | null
    lines?: BudgetLine[]
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateBudgetLinePayload {
    account_id: string
    expected_amount: number
    notes?: string | null
}

export interface CreateBudgetPayload {
    budget_number: string
    name: string
    start_date: string
    end_date: string
    notes?: string | null
    lines?: CreateBudgetLinePayload[]
}

export interface UpdateBudgetPayload {
    name?: string
    start_date?: string
    end_date?: string
    notes?: string | null
}

export interface UpdateBudgetLinePayload {
    expected_amount?: number
    notes?: string | null
}

// ----- Fiscal Period ----------------------------------------------------------

export type FiscalPeriodStatus = 'open' | 'locked'

export interface FiscalPeriodAccountSnapshot {
    id: string
    code: string
    name: string
    type: AccountType
}

export interface FiscalPeriodClosingJournalSnapshot {
    id: string
    referenceNumber: string
    description: string | null
    entryDate: string | null
    status: string
}

export interface FiscalPeriod {
    id: string
    periodNumber: string
    name: string
    startDate: string | null
    endDate: string | null
    status: FiscalPeriodStatus
    isClosable: boolean
    isReopenable: boolean
    lockedAt: string | null
    lockedBy: string | null
    retainedEarningsAccountId: string | null
    retainedEarningsAccount?: FiscalPeriodAccountSnapshot | null
    closingJournalEntryId: string | null
    closingJournalEntry?: FiscalPeriodClosingJournalSnapshot | null
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface FiscalPeriodClosingPreviewRow {
    account: FiscalPeriodAccountSnapshot
    amount: number
}

export interface FiscalPeriodClosingPreview {
    revenue: FiscalPeriodClosingPreviewRow[]
    expense: FiscalPeriodClosingPreviewRow[]
    net: number
    retainedDr: number
    retainedCr: number
}

export interface CreateFiscalPeriodPayload {
    period_number: string
    name: string
    start_date: string
    end_date: string
    notes?: string | null
}

export interface CloseFiscalPeriodPayload {
    retained_earnings_account_id: string
    notes?: string | null
}

export interface CreateBankAccountPayload {
    account_id?: string | null
    name: string
    bank_name: string
    branch?: string | null
    account_number?: string | null
    account_holder?: string | null
    swift?: string | null
    iban?: string | null
    currency?: string
    opening_balance?: number | null
    last_reconciled_at?: string | null
    last_reconciled_balance?: number | null
    notes?: string | null
    is_active?: boolean
    is_default?: boolean
}

export interface CreateJournalLinePayload {
    account_id: string
    debit?: number
    credit?: number
}

export interface CreateJournalEntryPayload {
    reference_number: string
    description?: string | null
    entry_date: string
    lines: CreateJournalLinePayload[]
}
