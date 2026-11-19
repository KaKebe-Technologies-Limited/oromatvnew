// "My profile": the optional details a person can save with Oroma TV (name, photo, email, phone and which updates they want).
// There is no login. The server gives this phone a secret token when the profile is made; it is shown once, kept here in secure storage and
// sent with every later request in the X-Profile-Token header. Stored with expo-secure-store; in a desktop browser preview, where that is not
// available, it falls back to localStorage and then to memory (the same as lib/moderation.js).
// Every action here resolves { ok: true, profile, message? } or { ok: false, code, message, fields } and never throws, so a screen only has
// to show the message. Changes run one after the other, so a late answer can never overwrite a newer one.
import { useSyncExternalStore } from 'react';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import api, { badResponse, errorCode, errorMessage, fieldErrors, profileHeaders as tokenHeader } from './api';
import { CONTACT } from './links';
import { disablePushNotifications, enablePushNotifications } from './notifications';
import { getViewerId } from './presence';

const KEYS = { token: 'oroma_profile_token', cache: 'oroma_profile_cache' };
const TOKEN_RE = /^[0-9a-f]{64}$/i;
const MAX_PHOTO_BYTES = 4 * 1024 * 1024; // the server's limit; a bigger file is refused before it is uploaded

const MESSAGES = {
  lost: 'We could not find your profile on this phone, please create it again.',
  exists: `A profile was already made on this phone, but it can no longer be opened here. Please email ${CONTACT.email} and we will help you.`,
  blocked: `Our team has blocked changes to this profile. If you think this is a mistake, please email ${CONTACT.email}.`,
  unavailable: 'Profiles are not available yet. Please try again later.',
  saveFailed: 'We could not save your profile. Please try again.',
  needTerms: 'Please agree to the Terms of Use to save your profile.',
};

const memory = {};
let state = { ready: false, token: null, profile: null, fetching: false, pending: 0, error: '', unavailable: false };
let loading = null;
let queue = Promise.resolve();
let version = 0; // counts profile changes, so a slow refresh that started before a change is dropped
let lastFetch = 0;
let inflightRefresh = null;
const listeners = new Set();

// ---- storage -------------------------------------------------------------------------------------------------------------

async function readKey(key) {
  try {
    const value = await SecureStore.getItemAsync(key);
    if (value != null) return value;
  } catch {
    // Secure storage is not available here (desktop preview): try the next place.
  }
  try {
    const value = globalThis.localStorage?.getItem(key);
    if (value != null) return value;
  } catch {
    // No localStorage either.
  }
  return memory[key] ?? null;
}

async function writeKey(key, value) {
  memory[key] = value;
  try {
    // This phone only: not copied to a new phone by a backup restore (the same is done for the viewer id in presence.js).
    await SecureStore.setItemAsync(key, value, { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });
    return;
  } catch {
    // fall through
  }
  try {
    globalThis.localStorage?.setItem(key, value);
  } catch {
    // Kept in memory for this session only.
  }
}

async function removeKey(key) {
  delete memory[key];
  try {
    await SecureStore.deleteItemAsync(key);
  } catch {
    // Nothing stored there, or not available.
  }
  try {
    globalThis.localStorage?.removeItem(key);
  } catch {
    // No localStorage.
  }
}

// ---- store ---------------------------------------------------------------------------------------------------------------

let snapshot = build();

function build() {
  return {
    ready: state.ready,
    profile: state.profile,
    hasProfile: !!state.token,
    loading: !state.ready || (!!state.token && !state.profile && state.fetching),
    saving: state.pending > 0,
    error: state.error,
    unavailable: state.unavailable,
    refresh,
    create,
    update,
    uploadPhoto,
    removePhoto,
    deleteProfile,
    resendEmail,
    registerPush,
    unregisterPush,
  };
}

function emit(patch) {
  state = { ...state, ...patch };
  snapshot = build();
  listeners.forEach((l) => l());
}

function subscribe(listener) {
  listeners.add(listener);
  load();
  return () => listeners.delete(listener);
}

/** `{ ready, profile, hasProfile, loading, saving, error, unavailable, refresh, create, update, uploadPhoto, removePhoto, deleteProfile, resendEmail, registerPush, unregisterPush }`, kept up to date wherever it is used. */
export const useProfile = () => useSyncExternalStore(subscribe, () => snapshot, () => snapshot);

function load() {
  if (!loading) {
    loading = (async () => {
      const [token, cache] = await Promise.all([readKey(KEYS.token), readKey(KEYS.cache)]);
      if (!token || !TOKEN_RE.test(token)) {
        if (cache) removeKey(KEYS.cache); // a saved profile without its token cannot be used
        emit({ ready: true });
        return;
      }
      let profile = null;
      try {
        const parsed = JSON.parse(cache);
        if (parsed && typeof parsed === 'object' && parsed.id != null) profile = normalise(parsed);
      } catch {
        // No usable saved copy: the profile is fetched when a screen asks for it.
      }
      emit({ ready: true, token, profile });
    })().catch(() => emit({ ready: true }));
  }
  return loading;
}

/** Runs a change after the saved values are loaded and after the change before it has finished. */
function exclusive(task) {
  emit({ pending: state.pending + 1 });
  const run = queue.then(load).then(task);
  queue = run.catch(() => {});
  return run
    .catch((e) => explain(e, MESSAGES.saveFailed))
    .finally(() => emit({ pending: state.pending - 1 }));
}

/** A copy the screens can rely on (the topics object always has its three keys). */
function normalise(p) {
  const topics = p.topics && typeof p.topics === 'object' ? p.topics : {};
  return { ...p, topics: { breaking: topics.breaking !== false, live: topics.live !== false, shows: topics.shows !== false } };
}

function setProfile(raw) {
  const profile = normalise(raw);
  version += 1;
  emit({ profile, error: '', unavailable: false });
  writeKey(KEYS.cache, JSON.stringify(profile));
  return profile;
}

/** The token and the saved copy are gone from this phone (deleted, or the server no longer knows this token). */
function forgetLocal() {
  version += 1;
  lastFetch = 0;
  emit({ token: null, profile: null, error: '', unavailable: false });
  return Promise.all([removeKey(KEYS.token), removeKey(KEYS.cache)]);
}

// ---- requests ------------------------------------------------------------------------------------------------------------

const fail = (code, message, fields = {}) => ({ ok: false, code, message, fields });

/** Turns any request error into a friendly { ok: false } result. A token the server does not know is forgotten here. */
function explain(e, fallback) {
  const status = e?.response?.status;
  const code = errorCode(e);
  if (status === 401 && code === 'bad_token') {
    forgetLocal();
    return fail('bad_token', MESSAGES.lost);
  }
  if (status === 409 && code === 'profile_exists') return fail('profile_exists', MESSAGES.exists);
  if (status === 403 && code === 'profile_blocked') return fail('profile_blocked', MESSAGES.blocked);
  if (status === 404 && !code) return fail('unavailable', MESSAGES.unavailable); // a server that does not have the profile routes yet
  if (status === 422) {
    const fields = fieldErrors(e);
    return fail('invalid', errorMessage(e, fallback), fields);
  }
  if (status === 429 && typeof e.response.data?.message === 'string') return fail('rate_limited', e.response.data.message); // the server says when to try again
  return fail(e?.response ? 'error' : 'network', errorMessage(e, fallback));
}

function profileFrom(body) {
  const profile = body?.data?.profile;
  if (!profile || typeof profile !== 'object' || profile.id == null) throw badResponse();
  return profile;
}

/** Sends one request with this phone's token. Resolves the parsed JSON body; a body that is not JSON (a hosting page) is a BAD_RESPONSE error. */
async function request(run) {
  const res = await run(tokenHeader(state.token));
  if (!res?.data || typeof res.data !== 'object') throw badResponse();
  return res.data;
}

const needToken = () => (state.token ? null : fail('bad_token', MESSAGES.lost));

const present = (v) => v !== undefined && v !== null && String(v).trim() !== '';

/** Makes the profile. The token in the answer is shown by the server only this once, so it is saved before anything else happens. */
function create(fields = {}) {
  return exclusive(async () => {
    if (state.token) return fail('profile_exists', 'This phone already has a profile.');
    if (fields.accepted_terms !== true) return fail('invalid', MESSAGES.needTerms, { accepted_terms: MESSAGES.needTerms });
    const body = { display_name: String(fields.display_name ?? '').trim(), accepted_terms: true, viewer_id: await getViewerId() };
    if (present(fields.email)) body.email = String(fields.email).trim();
    if (present(fields.phone)) body.phone = String(fields.phone).trim();
    if (fields.topics) body.topics = fields.topics;
    if (typeof fields.consent_email === 'boolean') body.consent_email = fields.consent_email;
    try {
      const data = await request(() => api.post('/profile', body));
      const profile = profileFrom(data);
      const token = data.data.token;
      if (typeof token !== 'string' || !TOKEN_RE.test(token)) throw badResponse();
      await writeKey(KEYS.token, token);
      emit({ token });
      lastFetch = Date.now();
      return { ok: true, profile: setProfile(profile), message: data.message || 'Your profile has been saved.' };
    } catch (e) {
      return explain(e, MESSAGES.saveFailed);
    }
  });
}

/** Changes the given fields ({ display_name, email, phone, topics, consent_email, consent_push }); the rest stay as they are. */
function update(fields = {}) {
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    try {
      const data = await request((headers) => api.patch('/profile', fields, { headers }));
      return { ok: true, profile: setProfile(profileFrom(data)), message: data.message || 'Your profile has been saved.' };
    } catch (e) {
      return explain(e, MESSAGES.saveFailed);
    }
  });
}

/** What goes in the form field for a picked photo: a file on the phone, or the File the browser gave. */
async function photoPart(source) {
  const asset = typeof source === 'string' ? { uri: source } : source || {};
  const uri = String(asset.uri || '');
  const mime = String(asset.mimeType || '').toLowerCase();
  const type = mime.startsWith('image/') ? mime : /\.png(\?|$)/i.test(uri) ? 'image/png' : /\.webp(\?|$)/i.test(uri) ? 'image/webp' : 'image/jpeg';
  const name = `photo.${type === 'image/png' ? 'png' : type === 'image/webp' ? 'webp' : 'jpg'}`;
  if (Platform.OS === 'web') return { file: asset.file || (await (await fetch(uri)).blob()), name };
  return { file: { uri, name, type }, name };
}

/** Uploads a picked photo (a picker asset, or its uri). It waits for staff approval and only its owner sees it until then. */
function uploadPhoto(source) {
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    const size = typeof source === 'object' ? Number(source?.fileSize) : 0;
    if (size > MAX_PHOTO_BYTES) {
      const message = 'That photo is too large. Please choose one under 4 MB.';
      return fail('invalid', message, { photo: message });
    }
    try {
      const { file, name } = await photoPart(source);
      const form = new FormData();
      if (Platform.OS === 'web') form.append('photo', file, name);
      else form.append('photo', file);
      const res = await api.upload('/profile/photo', form, { headers: tokenHeader(state.token) });
      return { ok: true, profile: setProfile(profileFrom(res.data)) };
    } catch (e) {
      return explain(e, 'We could not upload your photo. Please try again.');
    }
  });
}

function removePhoto() {
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    try {
      const data = await request((headers) => api.delete('/profile/photo', { headers }));
      return { ok: true, profile: setProfile(profileFrom(data)) };
    } catch (e) {
      return explain(e, 'We could not remove your photo. Please try again.');
    }
  });
}

/** Removes the profile from our server and from this phone. A published comment stays, without the picture. */
function deleteProfile() {
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return fail('bad_token', MESSAGES.lost);
    try {
      await request((headers) => api.delete('/profile', { headers }));
      await forgetLocal();
      await disablePushNotifications();
      return { ok: true, message: 'Your profile has been deleted.' };
    } catch (e) {
      const result = explain(e, 'We could not delete your profile. Please try again.');
      if (result.code === 'bad_token') {
        // The server no longer knows this phone's profile, so there is nothing left to delete there; the copy on the phone is already gone.
        return { ok: true, message: `Your profile has been removed from this phone. If it still shows in our records, email ${CONTACT.email} and we will delete it.` };
      }
      return result;
    }
  });
}

/** Sends the confirmation email again. */
function resendEmail() {
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    try {
      const data = await request((headers) => api.post('/profile/email/resend', {}, { headers }));
      return { ok: true, profile: setProfile(profileFrom(data)), message: data.message || 'We have sent a confirmation email. Please check your inbox.' };
    } catch (e) {
      return explain(e, 'We could not send the email just now. Please try again later.');
    }
  });
}

/**
 * Switches notifications on for this phone: asks the system for permission (only here, when the person asked for it), gets the push token and
 * gives it to the server together with the consent. `obtained` is the answer of enablePushNotifications() when the screen already asked
 * at the moment the switch was turned on.
 */
async function registerPush(obtained) {
  const got = obtained || (await enablePushNotifications());
  if (!got?.ok) return fail(got?.reason || 'error', got?.message || 'We could not switch notifications on. Please try again in a moment.');
  return exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    try {
      const data = await request((headers) => api.post('/profile/push-token', { token: got.token, platform: got.platform, consent_push: true }, { headers }));
      return { ok: true, profile: setProfile(profileFrom(data)) };
    } catch (e) {
      return explain(e, 'We could not switch notifications on. Please try again in a moment.');
    }
  });
}

/** Switches notifications off: the server forgets this phone's token, then the phone drops its registration. */
async function unregisterPush() {
  const result = await exclusive(async () => {
    const missing = needToken();
    if (missing) return missing;
    try {
      const data = await request((headers) => api.patch('/profile', { consent_push: false }, { headers }));
      return { ok: true, profile: setProfile(profileFrom(data)) };
    } catch (e) {
      return explain(e, 'We could not switch notifications off. Please try again.');
    }
  });
  if (result.ok) await disablePushNotifications();
  return result;
}

/**
 * Fetches the profile again (its photo may have been approved, its email confirmed). With `quiet` a failure is not shown and a slow
 * request does not show a spinner; `maxAgeMs` skips the request when the profile was fetched more recently than that.
 */
async function refresh({ quiet = false, maxAgeMs = 0 } = {}) {
  await load();
  if (!state.token) return fail('bad_token', MESSAGES.lost);
  if (maxAgeMs && state.profile && Date.now() - lastFetch < maxAgeMs) return { ok: true, profile: state.profile };
  if (inflightRefresh) return inflightRefresh;
  const startedAt = version;
  emit({ fetching: true, ...(quiet ? {} : { error: '', unavailable: false }) });
  inflightRefresh = (async () => {
    try {
      const data = await request((headers) => api.get('/profile', { headers }));
      const profile = profileFrom(data);
      lastFetch = Date.now();
      // A change made while this request was on its way is newer than this answer.
      return { ok: true, profile: version === startedAt ? setProfile(profile) : state.profile };
    } catch (e) {
      const result = explain(e, 'We could not load your profile. Please try again.');
      if (!quiet) emit({ error: result.message, unavailable: result.code === 'unavailable' });
      return result;
    } finally {
      inflightRefresh = null;
      emit({ fetching: false });
    }
  })();
  return inflightRefresh;
}

// ---- for other code ------------------------------------------------------------------------------------------------------

/** This phone's profile token, or null when there is no profile (read from storage once, then remembered). */
export async function getProfileToken() {
  await load();
  return state.token;
}

/** `{ 'X-Profile-Token': token }` for a request made on behalf of this profile, or `{}` when there is none (for example when posting a comment). */
export async function profileHeaders() {
  return tokenHeader(await getProfileToken());
}

load(); // start reading now, so the More tab already knows the saved name
