// Axios client for the Oroma TV PHP API (/api/v1) plus small helpers:
// friendly error messages, the /api/ping health check, and media URL fixing for local testing.
import axios from 'axios';
import Constants from 'expo-constants';

const ENV = Constants.expoConfig?.extra?.apiUrl || {};
// A release build is 'production' unless told otherwise; only a developer run (Expo Go / dev server) is 'development'.
const appEnv = process.env.EXPO_PUBLIC_APP_ENV || (__DEV__ ? 'development' : 'production');

/**
 * The one switch for developer-only help (the connection test in More, the "cannot reach <address>" error text).
 * __DEV__ is false in every build that is installed on a phone, the EAS "local-wifi" APK included (its APP_ENV is
 * "development" but it is still a release bundle), so only a run from the dev server can ever turn this on; setting
 * EXPO_PUBLIC_APP_ENV=production there switches it off to see what a store build shows.
 */
export const DEV_TOOLS = __DEV__ && appEnv !== 'production';

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
export const API_ROOT = ((configured && configured !== 'auto' ? configured : devServerApi()) || ENV[appEnv] || (__DEV__ ? ENV.development : ENV.production) || 'https://oromatv.com/api').replace(/\/+$/, '');
export const SITE_URL = API_ROOT.replace(/\/api$/, '');

const api = axios.create({
  baseURL: `${API_ROOT}/v1`,
  timeout: 15000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

// api.get / post / patch / delete are the axios ones, so extra headers go in the request config:
//   api.get('/profile', { headers: profileHeaders(token) })      api.patch('/profile', body, { headers })      api.delete('/profile', { headers })

/** The header that proves a request comes from the phone that owns the profile (the token the server gave when it was created). */
export const profileHeaders = (token) => (token ? { 'X-Profile-Token': String(token) } : {});

/**
 * Sends a multipart form (a picked photo) with POST. The instance default says JSON, which would turn the FormData into JSON, so the
 * type is set to multipart here; the phone (or the browser) then adds the boundary itself. Resolves with the axios response like
 * api.post, and rejects with badResponse() when the server answers 200 with something that is not JSON (an error page, a captive portal).
 *   const form = new FormData(); form.append('photo', { uri, name: 'photo.jpg', type: 'image/jpeg' });
 *   const { data } = await api.upload('/profile/photo', form, { headers: profileHeaders(token) });
 */
api.upload = async (path, formData, { headers, timeout = 60000, onUploadProgress, signal } = {}) => {
  const res = await api.post(path, formData, {
    headers: { ...headers, 'Content-Type': 'multipart/form-data' },
    timeout, // a photo on a slow mobile connection needs longer than the usual 15 seconds
    onUploadProgress,
    signal,
  });
  if (!res?.data || typeof res.data !== 'object') throw badResponse();
  return res;
};

/** Health check: GET /api/ping (not under /v1). Resolves with { status, version, ms }. */
export async function ping() {
  const started = Date.now();
  const { data } = await axios.get(`${API_ROOT}/ping`, { timeout: 8000, headers: { Accept: 'application/json' } });
  return { ...data, ms: Date.now() - started };
}

/** The server answered 200 but not with the JSON we expect (a captive-portal page, a hosting error page…). */
export const badResponse = () => Object.assign(new Error('Unexpected server answer'), { code: 'BAD_RESPONSE' });

/** A human-readable message for any request error (validation, throttling, network…). */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
  if (error?.code === 'BAD_RESPONSE') return 'We could not read the answer from Oroma TV. Please try again in a moment.';
  const res = error?.response;
  if (!res) {
    if (error?.code === 'ECONNABORTED') return 'The server took too long to answer. Please try again.';
    if (error?.message === 'Network Error') return DEV_TOOLS ? `Cannot reach the server at ${API_ROOT || 'the API'}. Check your connection and the address in app/.env.` : 'You appear to be offline, or Oroma TV cannot be reached right now. Please check your connection and try again.';
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

/** The machine-readable `code` of a JSON error body ({ message, code, data }), or null (an old server, a hosting page, no reply). */
export function errorCode(error) {
  const code = error?.response?.data?.code;
  return typeof code === 'string' ? code : null;
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
