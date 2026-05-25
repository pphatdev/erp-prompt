# Skill: Implementing Frontend Form Validation

## Context
This skill provides the concrete implementation patterns for adding form validation to a Nuxt 3 / PrimeVue component, adhering to the ERP's `rules/frontend/validation.md`.

## Step-by-Step Implementation (Vuelidate Pattern)

### 1. Setup State and Rules
Define your reactive form state and the corresponding validation rules using Vuelidate.

```vue
<script setup lang="ts">
import { reactive, computed } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, email, maxLength } from '@vuelidate/validators';

const state = reactive({
    firstName: '',
    lastName: '',
    emailAddress: '',
});

const rules = computed(() => ({
    firstName: { required, maxLength: maxLength(50) },
    lastName: { required, maxLength: maxLength(50) },
    emailAddress: { required, email }
}));

const v$ = useVuelidate(rules, state);
</script>
```

### 2. Template Integration (PrimeVue)
Bind the Vuelidate instance to your PrimeVue components. Use the `$invalid` and `$dirty` states to apply a prominent danger color (error styling) so the user instantly knows where the invalid field is.

```vue
<template>
    <form @submit.prevent="submitForm" class="flex flex-col gap-4">
        
        <!-- First Name Field -->
        <div class="field flex flex-col gap-1">
            <label for="firstName" class="font-medium">
                First Name <span class="text-red-500">*</span>
            </label>
            <InputText 
                id="firstName" 
                v-model="state.firstName" 
                :class="{ 'p-invalid': v$.firstName.$invalid && v$.firstName.$dirty }"
                @blur="v$.firstName.$touch()"
            />
            <small v-if="v$.firstName.$error" class="text-red-500">
                {{ v$.firstName.$errors[0].$message }}
            </small>
        </div>

        <!-- Submit Button -->
        <Button type="submit" label="Save" :disabled="v$.$invalid" />
    </form>
</template>
```

### 3. Handling Submission & Server Errors
When submitting, force validation. If valid, send the request and handle potential 422 errors from Laravel.

```typescript
const submitForm = async () => {
    // 1. Trigger all client-side validations
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    try {
        // 2. Submit to backend via useApi (automatically injects Tenant headers)
        await useApi('/api/users', {
            method: 'POST',
            body: state
        });
        
        toast.add({ severity: 'success', summary: 'Success', detail: 'User created' });
    } catch (error: any) {
        // 3. Handle 422 Validation Errors from Laravel
        if (error.response?.status === 422) {
            const serverErrors = error.response._data.errors;
            
            // Example of mapping server errors to a generic toast
            // For a better UX, map these specific field errors back into Vuelidate's $externalResults
            toast.add({ 
                severity: 'error', 
                summary: 'Validation Error', 
                detail: 'Please check the form for errors.' 
            });
        }
    }
};
```

## Cambodian Phone Number Validation & Normalization

To ensure consistent E.164-like formatting for Cambodian phone numbers before persisting or submitting, implement the following helper/method:

```typescript
/**
 * @description Normalizes Cambodian phone numbers to E.164 format before saving.
 * @param { String } phone Input phone number string
 * @returns { String } The normalized phone number in E.164 format (+855...)
 */
export const normalizePhoneNumber = (phone: string): string => {
    if (!phone) return '';
    
    // Remove all whitespace and formatting characters (non-digits and non-plus)
    const clean = phone.replace(/[^\d+]/g, '');

    // Rule 1: Starts with '0'
    if (clean.startsWith('0')) {
        return '+855' + clean.slice(1);
    }
    
    // Rule 3: Already starts with '+' (e.g., +855 or +NNN)
    if (clean.startsWith('+')) {
        return clean;
    }

    // Rule 2: Starts with country code or digits without '+'
    return '+' + clean;
};
```

## Email Validation & Normalization

To maintain consistent lowercase and whitespace-trimmed email entries across all forms, implement the following helper:

```typescript
/**
 * @description Normalizes email addresses before saving or submitting by trimming and converting to lowercase.
 * @param { String } email Input email string
 * @returns { String } The trimmed and lowercase email address
 */
export const normalizeEmail = (email: string): string => {
    if (!email) return '';
    return email.trim().toLowerCase();
};
```

## Checklist for Reviewers
- [ ] Are required fields marked with a red `*`?
- [ ] Does validation trigger on `blur` via `$touch()`?
- [ ] Are error messages displayed immediately below the respective fields in a danger color (`text-red-500`)?
- [ ] Do the invalid input fields themselves prominently show a danger color (e.g., `p-invalid` or `border-red-500`) so users know exactly where the error is?
- [ ] Are Laravel 422 errors gracefully handled and displayed to the user?
