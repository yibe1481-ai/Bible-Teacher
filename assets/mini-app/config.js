/**
 * Bible English Mini App — runtime configuration.
 *
 * API_BASE points at the WordPress REST namespace. Defaults to the same origin
 * (served from WordPress). Override via BE_API_BASE before this file loads if
 * the app is hosted on a separate origin.
 */
// Preserve a config injected server-side (BE_MiniApp router). Otherwise build
// a sensible default: root-relative WordPress REST namespace, which shares the
// page's origin & scheme so the Authorization header is never lost to CORS.
window.BE_CONFIG = window.BE_CONFIG || {
	API_BASE: window.BE_API_BASE || '/wp-json/be/v1',
};