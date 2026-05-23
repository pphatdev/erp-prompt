import { ref, computed } from 'vue'

export interface DashboardKpiEmployees {
  total: number
  active: number
  on_leave: number
  new_mtd: number
}
export interface DashboardKpiLeave {
  pending: number
  approved_mtd: number
  rejected_mtd: number
}
export interface DashboardKpiAttendance {
  present_today: number
}
export interface DashboardKpiSales {
  revenue_mtd: number
  revenue_prev_mtd: number
  revenue_change_pct: number
  orders_mtd: number
  active_customers: number
  open_leads: number
}
export interface DashboardKpiInventory {
  total_products: number
  low_stock: number
}
export interface DashboardKpiProjects {
  active: number
  open_tasks: number
  completed_tasks_mtd: number
}
export interface DashboardKpiFinance {
  total_accounts: number
  unposted_journals: number
}

export interface DashboardSummary {
  period: { start: string; end: string }
  active_module_slugs: string[]
  kpis: {
    employees?: DashboardKpiEmployees
    leave?: DashboardKpiLeave
    attendance?: DashboardKpiAttendance
    sales?: DashboardKpiSales
    inventory?: DashboardKpiInventory
    projects?: DashboardKpiProjects
    finance?: DashboardKpiFinance
  }
  charts: {
    revenue_trend: Array<{ label: string; amount: number }>
    headcount_by_dept: Array<{ label: string; count: number }>
  }
  recent: {
    orders: Array<{
      id: string
      number: string
      customer_name: string
      total: number
      status: string
      date: string | null
    }>
    leaves: Array<{
      id: string
      employee_name: string
      type: string
      days: number
      status: string
      start_date: string | null
    }>
  }
}

const _summary    = ref<DashboardSummary | null>(null)
const _loading    = ref(false)
const _error      = ref<string | null>(null)
const _lastLoaded = ref<number | null>(null)
const STALE_MS    = 5 * 60 * 1000 // 5 min

export const useDashboard = () => {
  const api = useApi()

  const load = async (force = false) => {
    const age = _lastLoaded.value ? Date.now() - _lastLoaded.value : Infinity
    if (!force && _summary.value && age < STALE_MS) return
    if (_loading.value) return
    _loading.value = true
    _error.value = null
    try {
      const res = await api.get<DashboardSummary>('dashboard/summary')
      _summary.value = res
      _lastLoaded.value = Date.now()
    } catch (e: unknown) {
      _error.value = e instanceof Error ? e.message : 'Failed to load dashboard'
    } finally {
      _loading.value = false
    }
  }

  const hasModule = (slug: string) =>
    _summary.value?.active_module_slugs.includes(slug) ?? false

  /** Revenue trend scaled to percentage heights for the bar chart. */
  const revenueBars = computed(() => {
    const trend = _summary.value?.charts.revenue_trend ?? []
    const max = Math.max(...trend.map(t => t.amount), 1)
    return trend.map(t => ({
      label:  t.label,
      amount: t.amount,
      height: Math.max(Math.round((t.amount / max) * 100), 4),
    }))
  })

  /** Headcount bars scaled to percentage widths. */
  const headcountBars = computed(() => {
    const rows = _summary.value?.charts.headcount_by_dept ?? []
    const max = Math.max(...rows.map(r => r.count), 1)
    return rows.map(r => ({
      label: r.label,
      count: r.count,
      width: Math.max(Math.round((r.count / max) * 100), 4),
    }))
  })

  const formatCurrency = (value: number) => {
    if (value >= 1_000_000) return `$${(value / 1_000_000).toFixed(1)}M`
    if (value >= 1_000) return `$${(value / 1_000).toFixed(1)}K`
    return `$${value.toFixed(2)}`
  }

  return {
    summary: _summary,
    loading: _loading,
    error:   _error,
    load,
    hasModule,
    revenueBars,
    headcountBars,
    formatCurrency,
  }
}
