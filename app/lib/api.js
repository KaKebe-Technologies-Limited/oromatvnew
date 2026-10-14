// Axios client for the Oroma TV PHP API (/api/v1) plus small helpers:
// friendly error messages, the /api/ping health check, and media URL fixing for local testing.
import axios from 'axios';
import Constants from 'expo-constants';

const ENV = Constants.expoConfig?.extra?.apiUrl || {};
const appEnv = process.env.EXPO_PUBLIC_APP_ENV || 'development';

/** The real website. Share links point here, and the live player / YouTube expect to be shown on it. */
export const PUBLIC_SITE_URL = 'https://oromatv.com';

/**
 * While developing, the computer that serves the app to the phone is also the one running XAMPP, so its address is
 * known from the dev server itself (for example 192.168.0.103:8081 -> http://192.168.0.103/oromatvnew/api). That way the
 * phone keeps working when the computer's address changes (home Wi-Fi, a phone hotspot, an office network).
 */
function devServerApi() {
  if (!__DEV__) return '';
  const fromBrowser = typeof document !== 'undefined' ? globalThis.location?.hostname : '';
  const host = String(Constants.expoConfig?.hostUri || Constants.expoGoConfig?.debuggerHost || fromBrowser || '').split(':')[0];

  return host && !host.includes('exp.direct') ? `http://${host}/oromatvnew/api` : '';
}

// EXPO_PUBLIC_API_URL wins (set it to a real address, or leave it as "auto" / empty while developing to use the address of the
// computer running the dev server); otherwise the per-environment URL from app.json.
const configured = (process.env.EXPO_PUBLIC_API_URL || '').trim();
export const API_ROOT = ((configured && configured !== 'auto' ? configured : devServerApi()) || ENV[appEnv] || ENV.development || '').replace(/\/+$/, '');
export const SITE_URL = API_ROOT.replace(/\/api$/, '');

const api = axios.create({
  baseURL: `${API_ROOT}/v1`,
  timeout: 15000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

/** Health check: GET /api/ping (not under /v1). Resolves with { status, version, ms }. */
export async function ping() {
  const started = Date.now();
  const { data } = await axios.get(`${API_ROOT}/ping`, { timeout: 8000, headers: { Accept: 'application/json' } });
  return { ...data, ms: Date.now() - started };
}

/** A human-readable message for any request error (validation, throttling, network…). */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const res = error?.response;
  if (!res) {
    if (error?.code === 'ECONNABORTED') return 'The server took too long to answer. Please try again.';
    if (error?.message === 'Network Error') return `Cannot reach the server at ${API_ROOT || 'the API'}. Check your connection and the address in app/.env.`;
    return error?.message || fallback;
  }
  if (res.status === 422) {
    const first = Object.values(res.data?.errors || {})[0];
    return (Array.isArray(first) ? first[0] : first) || res.data?.message || fallback;
  }
  if (res.status === 429) return 'Too many attempts. Please wait a while and try again.';
  if (res.status >= 500) return 'The server had a problem. Please try again shortly.';
  return res.data?.message || fallback;
}

/** Field-level validation errors from a 422: { email: 'Please enter a valid email.' }. */
export function fieldErrors(error) {
  if (error?.response?.status !== 422) return {};
  return Object.fromEntries(
    Object.entries(error.response.data?.errors || {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
  );
}

/**
 * Media URLs come from the server's own address. While testing on a phone that is sometimes "localhost",
 * which the phone cannot reach, so point those at the API host instead.
 */
export function mediaUrl(url) {
  if (!url || !SITE_URL) return url || null;
  return url.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, SITE_URL.replace(/(https?:\/\/[^/]+).*/, '$1'));
}

export default api;
