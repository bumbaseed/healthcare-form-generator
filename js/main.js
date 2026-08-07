/**
 * Healthcare Form Generator - Main JavaScript
 * Common JavaScript functionality
 */

// Mobile navigation toggle
document.addEventListener("DOMContentLoaded", function () {
    const navToggle = document.getElementById("navToggle");
    const navMenu = document.getElementById("navMenu");

    if (navToggle && navMenu) {
        navToggle.addEventListener("click", function () {
            const isOpen = navMenu.classList.toggle("active");
            navToggle.setAttribute("aria-expanded", String(isOpen));
        });
    }

    // Close nav menu when clicking outside
    document.addEventListener("click", function (event) {
        if (navMenu && !event.target.closest(".navbar")) {
            navMenu.classList.remove("active");
            if (navToggle) navToggle.setAttribute("aria-expanded", "false");
        }
    });

    // Dropdown toggle: clicks (mobile) and keyboard support on the button.
    // aria-expanded is mirrored on every change so screen-readers track state.
    const dropdownToggles = document.querySelectorAll(".dropdown-toggle");
    dropdownToggles.forEach(function (toggle) {
        const parent = toggle.closest(".dropdown");

        toggle.addEventListener("click", function (e) {
            e.preventDefault();
            const isOpen = parent.classList.toggle("active");
            toggle.setAttribute("aria-expanded", String(isOpen));
        });

        // Mirror desktop hover/focus state onto aria-expanded so SR users
        // hear the menu open when tabbing into it.
        parent.addEventListener("focusin", function () {
            toggle.setAttribute("aria-expanded", "true");
        });
        parent.addEventListener("focusout", function (e) {
            if (!parent.contains(e.relatedTarget)) {
                toggle.setAttribute("aria-expanded", "false");
            }
        });
    });

    // Auto-hide flash messages after 5 seconds
    const flashMessages = document.querySelectorAll(".flash-message");
    flashMessages.forEach(function (flash) {
        setTimeout(function () {
            flash.style.opacity = "0";
            flash.style.transition = "opacity 0.5s";
            setTimeout(function () {
                flash.remove();
            }, 500);
        }, 5000);
    });
});

/**
 * Confirm action before proceeding
 * @param {string} message - Confirmation message
 * @returns {boolean}
 */
function confirmAction(message) {
    return confirm(message || "Are you sure you want to proceed?");
}

/**
 * Show/hide loading indicator
 * @param {boolean} show - Whether to show or hide
 */
function toggleLoading(show) {
    const loadingEl = document.getElementById("loading");
    if (loadingEl) {
        loadingEl.style.display = show ? "flex" : "none";
    }
}

/**
 * Format date to display format
 * @param {string} dateString - Date string
 * @returns {string}
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    });
}

/**
 * Format datetime to display format
 * @param {string} datetimeString - Datetime string
 * @returns {string}
 */
function formatDatetime(datetimeString) {
    const date = new Date(datetimeString);
    return date.toLocaleString("en-US", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
}

/**
 * Debounce function for search inputs
 * @param {Function} func - Function to debounce
 * @param {number} wait - Milliseconds to wait
 * @returns {Function}
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Validate email format
 * @param {string} email - Email address
 * @returns {boolean}
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone format
 * @param {string} phone - Phone number
 * @returns {boolean}
 */
function isValidPhone(phone) {
    const cleaned = phone.replace(/\D/g, "");
    return cleaned.length >= 10;
}

/**
 * Show error message for form field
 * @param {HTMLElement} field - Form field element
 * @param {string} message - Error message
 */
function showFieldError(field, message) {
    // Remove existing error
    const existingError = field.parentElement.querySelector(".form-error");
    if (existingError) {
        existingError.remove();
    }

    // Add error class and message
    field.classList.add("error");
    const errorDiv = document.createElement("div");
    errorDiv.className = "form-error";
    errorDiv.textContent = message;
    field.parentElement.appendChild(errorDiv);
}

/**
 * Clear field error
 * @param {HTMLElement} field - Form field element
 */
function clearFieldError(field) {
    field.classList.remove("error");
    const errorDiv = field.parentElement.querySelector(".form-error");
    if (errorDiv) {
        errorDiv.remove();
    }
}
