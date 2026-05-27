/**
 * Global input validation and normalization rules for frontend forms.
 */

/**
 * @description Normalizes Cambodian phone numbers to E.164 format before saving.
 * @param { String | null | undefined } phone Input phone number string
 * @returns { String } The normalized phone number in E.164 format (+855...)
 */
export const normalizePhoneNumber = (phone: string | null | undefined): string => {
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

/**
 * @description Normalizes email addresses before saving or submitting by trimming and converting to lowercase.
 * @param { String | null | undefined } email Input email string
 * @returns { String } The trimmed and lowercase email address
 */
export const normalizeEmail = (email: string | null | undefined): string => {
    if (!email) return '';
    return email.trim().toLowerCase();
};

/**
 * @description Composable that exposes standard validation and normalization helpers for phone numbers and email addresses.
 * @returns { Object } Object containing validation/normalization helper functions
 */
export const useValidation = () => ({
    normalizePhoneNumber,
    normalizeEmail
});

