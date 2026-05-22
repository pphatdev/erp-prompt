# Frontend Validation Rules

## Context
Consistent data validation is critical for the ERP system to maintain data integrity and provide a premium user experience. This document outlines the mandatory rules for implementing frontend validation in Nuxt 3.

## 1. Libraries & Tools
- **Preferred Library**: `Vuelidate` (via `@vuelidate/core` and `@vuelidate/validators`) is the standard for form validation in this project.
- **Schema Validation**: `VeeValidate` paired with `zod` or `yup` is also permitted for highly complex, dynamic schemas.
- **Prohibition**: Do not hand-roll custom validation logic (e.g., `if (!email.includes('@'))`) for standard patterns. Always use established validator functions.

## 2. User Experience (UX) Standards
- **Immediate Feedback**: Validation must trigger on `blur` (when the user leaves the field) and on form `submit`.
- **Visual Cues (Danger Colors)**: 
  - To ensure users immediately know where the error is, the input field itself MUST show a prominent danger color on invalid states.
  - Invalid fields must have a red border and/or text (e.g., using `--p-danger-500`, `border-red-500`, or PrimeVue's `p-invalid` class).
  - A clear, concise error message must be displayed immediately below the field in a small, red font (`text-red-500`).
- **Required Indicators**: Labels for required fields must include a visible red asterisk (`*`).
- **Input Constraints**: Use PrimeVue's specialized components (e.g., `<InputNumber>`, `<Calendar>`) to physically prevent invalid characters from being typed, reducing the reliance on post-input validation.

## 3. Server-Side Error Integration (P0)
- Client-side validation is strictly for UX. The Laravel backend is the ultimate source of truth.
- **422 Unprocessable Entity**: The frontend API composable (`useApi`) must catch 422 responses.
- **Error Mapping**: The backend's validation errors (e.g., `{ "email": ["The email has already been taken."] }`) must be reactively mapped back to the UI fields so the user sees backend errors in the exact same format as frontend errors.

## 4. Common Validation Patterns
- **Strings**: Always define `maxLength` to match the database column limits (e.g., `maxLength(255)`).
- **Dates**: End dates must explicitly validate that they are `>=` the Start date.
- **Money/Currency**: Must be validated as numeric types and ideally input via `<InputNumber mode="currency">`.
