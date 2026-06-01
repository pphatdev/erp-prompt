import { useApi } from '~/composables/useApi'
import type {
    ExchangeRate,
    CreateExchangeRatePayload,
    ConvertResult,
    Account,
    AccountType,
    CreateAccountPayload,
    JournalEntry,
    JournalStatus,
    CreateJournalEntryPayload,
    ReverseJournalPayload,
    BankAccount,
    CreateBankAccountPayload,
    Bill,
    BillStatus,
    CreateBillPayload,
    BillPayment,
    BillPaymentStatus,
    CreateBillPaymentPayload,
    Reimbursement,
    ReimbursementStatus,
    CreateReimbursementPayload,
    CashAdvance,
    CashAdvanceStatus,
    CreateCashAdvancePayload,
    CashAdvanceSettlement,
    CashAdvanceSettlementStatus,
    CreateCashAdvanceSettlementPayload,
    Expense,
    ExpenseStatus,
    CreateExpensePayload,
    Receipt,
    ReceiptStatus,
    ReceiptOpenInvoice,
    CreateReceiptPayload,
    CreditNote,
    CreditNoteStatus,
    CreateCreditNotePayload,
    DebitNote,
    DebitNoteStatus,
    CreateDebitNotePayload,
    BankReconSession,
    BankReconStatus,
    BankReconStatementLine,
    BankReconPeriodLedgerEntry,
    CreateBankReconSessionPayload,
    CreateBankReconStatementLinePayload,
    Budget,
    BudgetStatus,
    BudgetLine,
    CreateBudgetPayload,
    UpdateBudgetPayload,
    CreateBudgetLinePayload,
    UpdateBudgetLinePayload,
    FiscalPeriod,
    FiscalPeriodStatus,
    FiscalPeriodClosingPreview,
    CreateFiscalPeriodPayload,
    CloseFiscalPeriodPayload,
    PaginatedResponse,
} from '~/types/finance'

interface ListQuery {
    page?: number
    limit?: number
    base_currency?: string
    quote_currency?: string
    from?: string
    to?: string
    is_active?: boolean | string
    search?: string
    type?: AccountType | string
    parent_id?: string | null
    tree?: boolean
    status?: JournalStatus | BillStatus | BillPaymentStatus | ReimbursementStatus | CashAdvanceStatus | CashAdvanceSettlementStatus | ExpenseStatus | ReceiptStatus | CreditNoteStatus | DebitNoteStatus | BankReconStatus | BudgetStatus | FiscalPeriodStatus | string
    bank_account_id?: string
    employee_id?: string
    cash_advance_id?: string
    customer_id?: string
    invoice_id?: string
    currency?: string
    default_only?: boolean | string
    supplier_id?: string
    po_id?: string
    open_only?: boolean | string
}

const buildQuery = (q: ListQuery = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v === undefined || v === null || v === '') continue
        params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useFinance = () => {
    const api = useApi()

    const exchangeRates = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<ExchangeRate>>(`exchange-rates${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: ExchangeRate }>(`exchange-rates/${id}`),

        create: (body: CreateExchangeRatePayload) =>
            api.post<{ data: ExchangeRate }>('exchange-rates', body),

        update: (id: string, body: Partial<CreateExchangeRatePayload>) =>
            api.put<{ data: ExchangeRate }>(`exchange-rates/${id}`, body),

        destroy: (id: string) =>
            api.delete(`exchange-rates/${id}`),

        latest: (base: string, quote: string, on?: string) =>
            api.get<{ data: ExchangeRate }>(
                `exchange-rates/latest?base_currency=${base}&quote_currency=${quote}${on ? `&on=${on}` : ''}`,
            ),

        convert: (amount: number, from: string, to: string, on?: string) =>
            api.get<{ data: ConvertResult }>(
                `exchange-rates/convert?amount=${amount}&from=${from}&to=${to}${on ? `&on=${on}` : ''}`,
            ),
    }

    const accounts = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Account>>(`accounts${buildQuery(q)}`),

        tree: () =>
            api.get<{ data: Account[] }>('accounts?tree=1'),

        show: (id: string) =>
            api.get<{ data: Account }>(`accounts/${id}`),

        create: (body: CreateAccountPayload) =>
            api.post<{ data: Account }>('accounts', body),

        update: (id: string, body: Partial<CreateAccountPayload>) =>
            api.put<{ data: Account }>(`accounts/${id}`, body),

        destroy: (id: string) =>
            api.delete(`accounts/${id}`),
    }

    const journals = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<JournalEntry>>(`ledger${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: JournalEntry }>(`ledger/${id}`),

        create: (body: CreateJournalEntryPayload) =>
            api.post<{ data: JournalEntry }>('ledger', body),

        reverse: (id: string, body: ReverseJournalPayload = {}) =>
            api.post<{ data: JournalEntry }>(`ledger/${id}/reverse`, body),
    }

    const bankAccounts = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<BankAccount>>(`bank-accounts${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: BankAccount }>(`bank-accounts/${id}`),

        create: (body: CreateBankAccountPayload) =>
            api.post<{ data: BankAccount }>('bank-accounts', body),

        update: (id: string, body: Partial<CreateBankAccountPayload>) =>
            api.put<{ data: BankAccount }>(`bank-accounts/${id}`, body),

        destroy: (id: string) =>
            api.delete(`bank-accounts/${id}`),
    }

    const bills = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Bill>>(`bills${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Bill }>(`bills/${id}`),

        create: (body: CreateBillPayload) =>
            api.post<{ data: Bill }>('bills', body),

        update: (id: string, body: Partial<CreateBillPayload>) =>
            api.put<{ data: Bill }>(`bills/${id}`, body),

        destroy: (id: string) =>
            api.delete(`bills/${id}`),

        approve: (id: string) =>
            api.post<{ data: Bill }>(`bills/${id}/approve`, {}),

        cancel: (id: string) =>
            api.post<{ data: Bill }>(`bills/${id}/cancel`, {}),
    }

    const billPayments = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<BillPayment>>(`bill-payments${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: BillPayment }>(`bill-payments/${id}`),

        create: (body: CreateBillPaymentPayload) =>
            api.post<{ data: BillPayment }>('bill-payments', body),

        cancel: (id: string) =>
            api.post<{ data: BillPayment }>(`bill-payments/${id}/cancel`, {}),
    }

    const reimbursements = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Reimbursement>>(`reimbursements${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Reimbursement }>(`reimbursements/${id}`),

        create: (body: CreateReimbursementPayload) =>
            api.post<{ data: Reimbursement }>('reimbursements', body),

        cancel: (id: string) =>
            api.post<{ data: Reimbursement }>(`reimbursements/${id}/cancel`, {}),
    }

    const cashAdvances = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CashAdvance>>(`cash-advances${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CashAdvance }>(`cash-advances/${id}`),

        create: (body: CreateCashAdvancePayload) =>
            api.post<{ data: CashAdvance }>('cash-advances', body),

        cancel: (id: string) =>
            api.post<{ data: CashAdvance }>(`cash-advances/${id}/cancel`, {}),
    }

    const cashAdvanceSettlements = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CashAdvanceSettlement>>(`cash-advance-settlements${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CashAdvanceSettlement }>(`cash-advance-settlements/${id}`),

        create: (body: CreateCashAdvanceSettlementPayload) =>
            api.post<{ data: CashAdvanceSettlement }>('cash-advance-settlements', body),

        cancel: (id: string) =>
            api.post<{ data: CashAdvanceSettlement }>(`cash-advance-settlements/${id}/cancel`, {}),
    }

    const expenses = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Expense>>(`expenses${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Expense }>(`expenses/${id}`),

        create: (body: CreateExpensePayload) =>
            api.post<{ data: Expense }>('expenses', body),

        cancel: (id: string) =>
            api.post<{ data: Expense }>(`expenses/${id}/cancel`, {}),
    }

    const receipts = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Receipt>>(`receipts${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Receipt }>(`receipts/${id}`),

        create: (body: CreateReceiptPayload) =>
            api.post<{ data: Receipt }>('receipts', body),

        cancel: (id: string) =>
            api.post<{ data: Receipt }>(`receipts/${id}/cancel`, {}),

        openInvoicesForCustomer: (customerId: string) =>
            api.get<{ data: ReceiptOpenInvoice[] }>(`receipts/open-invoices/${customerId}`),
    }

    const creditNotes = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CreditNote>>(`credit-notes${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CreditNote }>(`credit-notes/${id}`),

        create: (body: CreateCreditNotePayload) =>
            api.post<{ data: CreditNote }>('credit-notes', body),

        cancel: (id: string) =>
            api.post<{ data: CreditNote }>(`credit-notes/${id}/cancel`, {}),
    }

    const debitNotes = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<DebitNote>>(`debit-notes${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: DebitNote }>(`debit-notes/${id}`),

        create: (body: CreateDebitNotePayload) =>
            api.post<{ data: DebitNote }>('debit-notes', body),

        cancel: (id: string) =>
            api.post<{ data: DebitNote }>(`debit-notes/${id}/cancel`, {}),
    }

    const bankReconciliations = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<BankReconSession>>(`bank-reconciliations${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: BankReconSession }>(`bank-reconciliations/${id}`),

        create: (body: CreateBankReconSessionPayload) =>
            api.post<{ data: BankReconSession }>('bank-reconciliations', body),

        close: (id: string) =>
            api.post<{ data: BankReconSession }>(`bank-reconciliations/${id}/close`, {}),

        reopen: (id: string) =>
            api.post<{ data: BankReconSession }>(`bank-reconciliations/${id}/reopen`, {}),

        addLine: (sessionId: string, body: CreateBankReconStatementLinePayload) =>
            api.post<{ data: BankReconStatementLine }>(`bank-reconciliations/${sessionId}/statement-lines`, body),

        removeLine: (lineId: string) =>
            api.delete(`bank-reconciliation-statement-lines/${lineId}`),

        matchLine: (lineId: string, ledgerEntryId: string) =>
            api.post<{ data: BankReconStatementLine }>(`bank-reconciliation-statement-lines/${lineId}/match`, { ledger_entry_id: ledgerEntryId }),

        unmatchLine: (lineId: string) =>
            api.post<{ data: BankReconStatementLine }>(`bank-reconciliation-statement-lines/${lineId}/unmatch`, {}),

        periodLedgerEntries: (sessionId: string) =>
            api.get<{ data: BankReconPeriodLedgerEntry[] }>(`bank-reconciliations/${sessionId}/period-ledger-entries`),
    }

    const budgets = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Budget>>(`budgets${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Budget }>(`budgets/${id}`),

        create: (body: CreateBudgetPayload) =>
            api.post<{ data: Budget }>('budgets', body),

        update: (id: string, body: UpdateBudgetPayload) =>
            api.put<{ data: Budget }>(`budgets/${id}`, body),

        destroy: (id: string) =>
            api.delete(`budgets/${id}`),

        activate: (id: string) =>
            api.post<{ data: Budget }>(`budgets/${id}/activate`, {}),

        archive: (id: string) =>
            api.post<{ data: Budget }>(`budgets/${id}/archive`, {}),

        variance: (id: string) =>
            api.get<{ data: Budget }>(`budgets/${id}/variance`),

        addLine: (budgetId: string, body: CreateBudgetLinePayload) =>
            api.post<{ data: BudgetLine }>(`budgets/${budgetId}/lines`, body),

        updateLine: (lineId: string, body: UpdateBudgetLinePayload) =>
            api.patch<{ data: BudgetLine }>(`budget-lines/${lineId}`, body),

        removeLine: (lineId: string) =>
            api.delete(`budget-lines/${lineId}`),
    }

    const fiscalPeriods = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<FiscalPeriod>>(`fiscal-periods${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: FiscalPeriod }>(`fiscal-periods/${id}`),

        create: (body: CreateFiscalPeriodPayload) =>
            api.post<{ data: FiscalPeriod }>('fiscal-periods', body),

        update: (id: string, body: Partial<CreateFiscalPeriodPayload>) =>
            api.put<{ data: FiscalPeriod }>(`fiscal-periods/${id}`, body),

        destroy: (id: string) =>
            api.delete(`fiscal-periods/${id}`),

        closingPreview: (id: string, retainedEarningsAccountId: string) =>
            api.get<{ data: FiscalPeriodClosingPreview }>(
                `fiscal-periods/${id}/closing-preview?retained_earnings_account_id=${retainedEarningsAccountId}`
            ),

        close: (id: string, body: CloseFiscalPeriodPayload) =>
            api.post<{ data: FiscalPeriod }>(`fiscal-periods/${id}/close`, body),

        reopen: (id: string) =>
            api.post<{ data: FiscalPeriod }>(`fiscal-periods/${id}/reopen`, {}),
    }

    return { exchangeRates, accounts, journals, bankAccounts, bills, billPayments, reimbursements, cashAdvances, cashAdvanceSettlements, expenses, receipts, creditNotes, debitNotes, bankReconciliations, budgets, fiscalPeriods }
}

export const ACCOUNT_TYPES: { value: AccountType; label: string; badge: string; icon: string }[] = [
    { value: 'asset',     label: 'Asset',     badge: 'badge-soft-info',    icon: 'ti-building-bank' },
    { value: 'liability', label: 'Liability', badge: 'badge-soft-warning', icon: 'ti-scale' },
    { value: 'equity',    label: 'Equity',    badge: 'badge-soft-primary', icon: 'ti-pie-chart' },
    { value: 'revenue',   label: 'Revenue',   badge: 'badge-soft-success', icon: 'ti-trending-up' },
    { value: 'expense',   label: 'Expense',   badge: 'badge-soft-danger',  icon: 'ti-trending-down' },
]

// Display constants reused by the Exchange Rates UI.
export const COMMON_CURRENCIES: { code: string; label: string }[] = [
    { code: 'USD', label: 'US Dollar' },
    { code: 'KHR', label: 'Cambodian Riel' },
    { code: 'EUR', label: 'Euro' },
    { code: 'GBP', label: 'British Pound' },
    { code: 'JPY', label: 'Japanese Yen' },
    { code: 'CNY', label: 'Chinese Yuan' },
    { code: 'THB', label: 'Thai Baht' },
    { code: 'VND', label: 'Vietnamese Dong' },
    { code: 'SGD', label: 'Singapore Dollar' },
    { code: 'AUD', label: 'Australian Dollar' },
]
