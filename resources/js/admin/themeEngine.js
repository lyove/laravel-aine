/**
 * Theme Engine — Runtime theme loading and management
 *
 * This module is the bridge between the backend ThemeService and the frontend
 * viewRegistry. It handles:
 *
 *   1. Fetching theme configuration from the API when a project is loaded
 *   2. Registering the theme with viewRegistry (runtime registration)
 *   3. Injecting design tokens as CSS custom properties on <html>
 *   4. Handling theme switching (when user changes theme in settings)
 *   5. Providing theme metadata for the UI (theme selector, etc.)
 *
 * ## Lifecycle
 *
 * When a user enters a project:
 *   store.setCurrentProject(id)
 *     → themeEngine.loadProjectTheme(project)
 *       → Fetch theme config from API
 *       → registerTheme(slug, themeData) in viewRegistry
 *       → injectDesignTokens(tokens)
 *       → Routes resolve views using the registered theme
 *
 * When a user changes the theme:
 *   themeEngine.applyTheme(projectId, newThemeId)
 *     → API call to apply new theme
 *     → Re-register with viewRegistry
 *     → Re-inject design tokens
 *     → Force re-render of current route
 */

import axios from 'axios';
import { registerTheme, unregisterTheme, getThemeDesignTokens } from './viewRegistry';

// ─── State ────────────────────────────────────────────────────────────────────

let currentThemeSlug = null;
let currentDesignTokens = {};
let styleElement = null;

// ─── Design Token Injection ───────────────────────────────────────────────────

/**
 * Inject design tokens as CSS custom properties on the <html> element.
 * These tokens can be used by any component via var(--theme-color-primary), etc.
 *
 * @param {object} tokens - Map of CSS variable names to values
 *   e.g. { "--theme-color-primary": "#4f46e5", ... }
 */
export function injectDesignTokens(tokens) {
    if (!tokens || Object.keys(tokens).length === 0) {
        removeDesignTokens();
        return;
    }

    currentDesignTokens = { ...tokens };

    // Remove existing style element if present
    removeDesignTokens();

    // Build CSS string
    const cssVars = Object.entries(tokens)
        .map(([key, value]) => `  ${key}: ${value};`)
        .join('\n');

    const css = `:root {\n${cssVars}\n}`;

    // Create and inject <style> element
    styleElement = document.createElement('style');
    styleElement.id = 'aine-theme-tokens';
    styleElement.setAttribute('data-theme', currentThemeSlug || '');
    styleElement.textContent = css;
    document.head.appendChild(styleElement);
}

/**
 * Remove injected design tokens from the DOM.
 */
export function removeDesignTokens() {
    const existing = document.getElementById('aine-theme-tokens');
    if (existing) {
        existing.remove();
    }
    styleElement = null;
}

/**
 * Get the currently active design tokens.
 */
export function getActiveDesignTokens() {
    return { ...currentDesignTokens };
}

// ─── Theme Loading ────────────────────────────────────────────────────────────

/**
 * Load and register the theme for a project.
 * Called when the user enters a project (via store.setCurrentProject).
 *
 * @param {object} project - Project data from API (must include theme info)
 * @returns {object|null} Theme config or null if no theme
 */
export async function loadProjectTheme(project) {
    if (!project?.id) {
        clearCurrentTheme();
        return null;
    }

    try {
        const { data } = await axios.get(`projects/themes/${project.id}`);

        if (!data.current_theme) {
            clearCurrentTheme();
            return null;
        }

        const themeConfig = data.current_theme;

        // Register the theme with viewRegistry (runtime registration)
        registerTheme(themeConfig.slug, themeConfig);

        // Update current theme state
        currentThemeSlug = themeConfig.slug;

        // Inject design tokens
        injectDesignTokens(themeConfig.design_tokens);

        return themeConfig;
    } catch (error) {
        console.warn('[themeEngine] Failed to load project theme:', error);
        clearCurrentTheme();
        return null;
    }
}

/**
 * Clear the current theme registration and design tokens.
 */
export function clearCurrentTheme() {
    if (currentThemeSlug) {
        // Don't unregister — other projects might use the same theme
        // Just clear the active state
    }
    currentThemeSlug = null;
    currentDesignTokens = {};
    removeDesignTokens();
}

// ─── Theme Management ─────────────────────────────────────────────────────────

/**
 * Apply a new theme to a project.
 *
 * @param {number} projectId
 * @param {number} themeId - The theme ID to apply
 * @returns {object} Updated project and theme config
 */
export async function applyTheme(projectId, themeId) {
    const { data } = await axios.post(`projects/themes/${projectId}/apply`, {
        theme_id: themeId,
    });

    // Register the new theme
    if (data.theme_config) {
        registerTheme(data.theme_config.slug, data.theme_config);
        currentThemeSlug = data.theme_config.slug;
        injectDesignTokens(data.theme_config.design_tokens);
    }

    return data;
}

/**
 * Update per-project theme configuration (design token overrides).
 *
 * @param {number} projectId
 * @param {object} themeConfig - Design token overrides
 * @returns {object} Updated merged tokens
 */
export async function updateThemeConfig(projectId, themeConfig) {
    const { data } = await axios.post(`projects/themes/${projectId}/config`, {
        theme_config: themeConfig,
    });

    // Re-inject the merged tokens
    if (data.merged_tokens) {
        injectDesignTokens(data.merged_tokens);
    }

    return data;
}

/**
 * Get all available themes from the backend.
 *
 * @returns {Array} List of available themes
 */
export async function fetchAvailableThemes() {
    const { data } = await axios.get('themes');
    return data;
}

/**
 * Trigger a re-scan of theme packages on the filesystem.
 * (Super admin only)
 *
 * @returns {object} Sync result with discovered themes
 */
export async function syncThemes() {
    const { data } = await axios.post('themes/sync');
    return data;
}

// ─── Getters ──────────────────────────────────────────────────────────────────

/**
 * Get the currently active theme slug.
 */
export function getCurrentThemeSlug() {
    return currentThemeSlug;
}

/**
 * Check if a theme is currently active.
 */
export function isThemeActive(slug) {
    return currentThemeSlug === slug;
}

/**
 * Get the design tokens for the currently active theme.
 */
export function getCurrentThemeTokens() {
    return getThemeDesignTokens(currentThemeSlug);
}

// ─── Default Export ───────────────────────────────────────────────────────────

export default {
    // Loading
    loadProjectTheme,
    clearCurrentTheme,

    // Management
    applyTheme,
    updateThemeConfig,
    fetchAvailableThemes,
    syncThemes,

    // Design Tokens
    injectDesignTokens,
    removeDesignTokens,
    getActiveDesignTokens,

    // Getters
    getCurrentThemeSlug,
    isThemeActive,
    getCurrentThemeTokens,
};
