<?php
/**
 * NOXARA - CSRF Protection
 */

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCsrf(): bool {
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

/**
 * CSRF field HTML (pakai di setiap form)
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

/**
 * Require valid CSRF — die jika invalid
 */
function requireCsrf(): void {
    if (!validateCsrf()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }
}

/**
 * Refresh token setelah submit form
 */
function refreshCsrfToken(): void {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
