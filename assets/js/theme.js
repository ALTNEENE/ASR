/**
 * A.S.R Theme System
 * Handles light/dark mode with localStorage persistence and system preference detection
 */

(function () {
    'use strict';

    const THEME_KEY = 'theme';
    const THEME_ATTR = 'data-theme';

    /**
     * Get system color scheme preference
     * @returns {string} 'dark' or 'light'
     */
    function getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    /**
     * Get current theme with priority:
     * 1. localStorage (user choice)
     * 2. System preference
     * 3. Default: light
     * @returns {string} 'dark' or 'light'
     */
    function getCurrentTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        if (savedTheme === 'dark' || savedTheme === 'light') {
            return savedTheme;
        }
        return getSystemTheme();
    }

    /**
     * Apply theme to document
     * @param {string} theme 'dark' or 'light'
     */
    function applyTheme(theme) {
        document.documentElement.setAttribute(THEME_ATTR, theme);
    }

    /**
     * Update theme toggle button icon
     * @param {string} theme current theme
     */
    function updateIcon(theme) {
        const icon = document.getElementById('theme-icon');
        if (!icon) return;

        // If theme is dark, show sun (to switch to light)
        // If theme is light, show moon (to switch to dark)
        icon.textContent = theme === 'dark' ? '☀️' : '🌙';

        const btn = document.getElementById('theme-toggle');
        if (btn) {
            btn.setAttribute('aria-label', theme === 'dark' ? 'تفعيل الوضع النهاري' : 'تفعيل الوضع الليلي');
            btn.setAttribute('title', theme === 'dark' ? 'تفعيل الوضع النهاري' : 'تفعيل الوضع الليلي');
        }
    }

    /**
     * Toggle theme between light and dark
     */
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute(THEME_ATTR) || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        applyTheme(newTheme);
        localStorage.setItem(THEME_KEY, newTheme);
        updateIcon(newTheme);

        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: newTheme } }));
    }

    /**
     * Initialize theme system
     */
    function initTheme() {
        const theme = getCurrentTheme();
        applyTheme(theme);
        updateIcon(theme);
    }

    /**
     * Listen for system theme changes
     */
    function watchSystemTheme() {
        if (!window.matchMedia) return;

        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        darkModeQuery.addEventListener('change', (e) => {
            // Only react to system changes if user hasn't manually set a theme
            const savedTheme = localStorage.getItem(THEME_KEY);
            if (!savedTheme) {
                const newTheme = e.matches ? 'dark' : 'light';
                applyTheme(newTheme);
                updateIcon(newTheme);
            }
        });
    }

    // Initialize immediately (before DOM loads) to prevent flash
    initTheme();

    // Setup event listeners when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('theme-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', toggleTheme);
            }
            watchSystemTheme();
        });
    } else {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleTheme);
        }
        watchSystemTheme();
    }

    // Expose toggle function globally for inline handlers (fallback)
    window.toggleTheme = toggleTheme;
    window.getCurrentTheme = getCurrentTheme;

})();
