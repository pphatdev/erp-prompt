1. Read `rules`, `skills`, and `AGENTS.md` before `CODEBASE`
2. Read dir: `.task` if exist, continue to update the current feature `pharse`, or `slide`
3. if not exist create task feature folder, context.md, task.md, and update `task.md` progress checklist
4. Always Sync `.task` with `CODEBASE`

5. Always use **Context7** to search for information and use it in the response, in markdown format, inside ````markdown` ````, 
6. Always use **Codebase Search** to search for information in codebase and use it in the response, in markdown format, inside ````markdown` ````

## 7. Always Use Standardized Agent Skills
When implementing a new feature or updating an existing one, you MUST refer to the `skills/` directory.
- **Standardized Documentation**: Every feature must have `rules.md`, `flow.md`, and `testing.md` in its module folder under `skills/features/`.
- **Workflow Integrity**: Follow the step-by-step flows defined in Mermaid diagrams for all business logic implementation.
- **Permission Mapping**: Use the `module.feature.action` pattern defined in `iam.md` for all authorization logic.