export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed'
export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'done'
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface EmployeeSnapshot {
    id: string
    employeeId: string | null
    fullName: string | null
}

export interface ProjectTaskSnapshot {
    id: string
    name: string
    status: ProjectStatus
}

export interface TaskProjectSnapshot {
    id: string
    name: string
    status: ProjectStatus
}

export interface TimesheetTaskSnapshot {
    id: string
    title: string
    status: TaskStatus
    projectId: string
}

export interface Project {
    id: string
    name: string
    description: string | null
    startDate: string | null
    endDate: string | null
    budget: number
    status: ProjectStatus
    managerId: string | null
    manager?: EmployeeSnapshot | null
    tasksCount: number | null
    tasks?: Task[]
    createdAt: string | null
    updatedAt: string | null
}

export interface Task {
    id: string
    projectId: string
    project?: TaskProjectSnapshot | null
    title: string
    description: string | null
    dueDate: string | null
    status: TaskStatus
    priority: TaskPriority
    assigneeId: string | null
    assignee?: EmployeeSnapshot | null
    timesheets?: Timesheet[]
    createdAt: string | null
    updatedAt: string | null
}

export interface Timesheet {
    id: string
    taskId: string
    task?: TimesheetTaskSnapshot | null
    employeeId: string
    employee?: EmployeeSnapshot | null
    logDate: string
    hoursWorked: number
    notes: string | null
    createdAt: string | null
}

export interface ProjectsKpis {
    total: number
    active: number
    completed: number
    overBudget: number
    unassignedTasks: number
    hoursThisMonth: number
}

export interface ProjectBudgetStatus {
    budget: number
    actual_cost: number
    variance: number
    percentage_used: number
}

export interface CreateProjectPayload {
    name: string
    description?: string | null
    start_date?: string | null
    end_date?: string | null
    budget?: number | null
    status?: ProjectStatus
    manager_id?: string | null
}

export type UpdateProjectPayload = Partial<CreateProjectPayload>

export interface CreateTaskPayload {
    project_id: string
    title: string
    description?: string | null
    due_date?: string | null
    status?: TaskStatus
    priority?: TaskPriority
    assignee_id?: string | null
}

export type UpdateTaskPayload = Partial<Omit<CreateTaskPayload, 'project_id'>> & { project_id?: string }

export interface CreateTimesheetPayload {
    task_id: string
    employee_id: string
    log_date: string
    hours_worked: number
    notes?: string | null
}

export type UpdateTimesheetPayload = Partial<CreateTimesheetPayload>

export interface ProjectsListQuery {
    page?: number
    limit?: number
    search?: string
    status?: ProjectStatus | string
    manager_id?: string
}

export interface TasksListQuery {
    page?: number
    limit?: number
    project_id?: string
    status?: TaskStatus | string
    assignee_id?: string
    priority?: TaskPriority | string
    search?: string
}

export interface TimesheetsListQuery {
    page?: number
    limit?: number
    task_id?: string
    employee_id?: string
    from?: string
    to?: string
}
