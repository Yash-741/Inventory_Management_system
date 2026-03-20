/**
 * Login Page - Frontend JavaScript
 * Handles form validation, password toggle, and demo login
 */

// DOM Elements
const loginForm = document.getElementById('loginForm');
const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');
const togglePasswordBtn = document.getElementById('togglePassword');
const demoBtn = document.getElementById('demoBtn');
const errorAlert = document.getElementById('errorAlert');
const successAlert = document.getElementById('successAlert');
const errorMessage = document.getElementById('errorMessage');
const successMessage = document.getElementById('successMessage');

// ============================================
// Event Listeners
// ============================================

/**
 * Toggle password visibility
 */
togglePasswordBtn.addEventListener('click', function(e) {
    e.preventDefault();
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.textContent = type === 'password' ? 'Show' : 'Hide';
});

/**
 * Handle form submission
 */
loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    clearErrors();
    
    // Validate inputs
    if (!validateForm()) {
        return;
    }
    
    // Disable submit button and show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';
    
    // Prepare form data
    const formData = new FormData(this);
    
    // Send login request
    fetch('api/auth.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store auth info in localStorage
            localStorage.setItem('authenticated', 'true');
            localStorage.setItem('user_id', data.data.user_id);
            localStorage.setItem('username', data.data.username);
            localStorage.setItem('role', data.data.role);
            localStorage.setItem('auth_token', data.data.auth_token);
            
            showSuccess('Login successful! Redirecting...');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            showError(data.message || 'Login failed. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Login error:', error);
        showError('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
});

/**
 * Handle demo login
 */
demoBtn.addEventListener('click', function(e) {
    e.preventDefault();
    usernameInput.value = 'admin';
    passwordInput.value = 'password123';
    passwordInput.type = 'text';
    togglePasswordBtn.textContent = 'Hide';
    
    // Automatically submit the form after short delay
    setTimeout(() => {
        loginForm.dispatchEvent(new Event('submit'));
    }, 300);
});

// ============================================
// Validation Functions
// ============================================

/**
 * Validate entire form
 */
function validateForm() {
    let isValid = true;
    
    // Validate username
    if (!validateUsername()) {
        isValid = false;
    }
    
    // Validate password
    if (!validatePassword()) {
        isValid = false;
    }
    
    return isValid;
}

/**
 * Validate username
 */
function validateUsername() {
    const username = usernameInput.value.trim();
    const usernameError = document.getElementById('usernameError');
    
    if (!username) {
        usernameError.textContent = 'Username is required';
        return false;
    }
    
    if (username.length < 3) {
        usernameError.textContent = 'Username must be at least 3 characters';
        return false;
    }
    
    if (username.length > 50) {
        usernameError.textContent = 'Username must not exceed 50 characters';
        return false;
    }
    
    // Check for valid characters (alphanumeric, underscore, dash)
    if (!/^[a-zA-Z0-9_-]+$/.test(username)) {
        usernameError.textContent = 'Username can only contain letters, numbers, underscore, and dash';
        return false;
    }
    
    return true;
}

/**
 * Validate password
 */
function validatePassword() {
    const password = passwordInput.value;
    const passwordError = document.getElementById('passwordError');
    
    if (!password) {
        passwordError.textContent = 'Password is required';
        return false;
    }
    
    if (password.length < 6) {
        passwordError.textContent = 'Password must be at least 6 characters';
        return false;
    }
    
    return true;
}

/**
 * Clear all error messages
 */
function clearErrors() {
    document.getElementById('usernameError').textContent = '';
    document.getElementById('passwordError').textContent = '';
}

// ============================================
// Alert Functions
// ============================================

/**
 * Show error alert
 */
function showError(message) {
    errorMessage.textContent = message;
    errorAlert.style.display = 'flex';
    
    // Auto-hide after 5 seconds
    setTimeout(closeAlert, 5000);
}

/**
 * Show success alert
 */
function showSuccess(message) {
    successMessage.textContent = message;
    successAlert.style.display = 'flex';
}

/**
 * Close alert
 */
function closeAlert() {
    errorAlert.style.display = 'none';
    successAlert.style.display = 'none';
}

// ============================================
// Real-time Validation
// ============================================

/**
 * Validate username on input
 */
usernameInput.addEventListener('blur', validateUsername);
usernameInput.addEventListener('input', function() {
    if (this.value.trim()) {
        validateUsername();
    } else {
        document.getElementById('usernameError').textContent = '';
    }
});

/**
 * Validate password on input
 */
passwordInput.addEventListener('blur', validatePassword);
passwordInput.addEventListener('input', function() {
    if (this.value) {
        validatePassword();
    } else {
        document.getElementById('passwordError').textContent = '';
    }
});

// ============================================
// Session Management
// ============================================

/**
 * Check if user is already logged in
 * If yes, redirect to dashboard
 */
window.addEventListener('load', function() {
    // Check localStorage for auth
    if (localStorage.getItem('authenticated') === 'true') {
        console.log('User already authenticated, redirecting to dashboard');
        window.location.href = 'index.html';
        return;
    }
    
    // Auto-login demo user on load (optional feature)
    const autoDemo = new URLSearchParams(window.location.search).get('demo');
    if (autoDemo === 'true') {
        setTimeout(() => {
            usernameInput.value = 'admin';
            passwordInput.value = 'password123';
            loginForm.dispatchEvent(new Event('submit'));
        }, 500);
    }
});

// ============================================
// Accessibility Features
// ============================================

/**
 * Focus on first input on page load
 */
window.addEventListener('load', function() {
    usernameInput.focus();
});

/**
 * Allow Enter key to submit form
 */
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && document.activeElement === passwordInput) {
        loginForm.dispatchEvent(new Event('submit'));
    }
});

/**
 * Log page load for debugging
 */
console.log('Login page loaded successfully');
