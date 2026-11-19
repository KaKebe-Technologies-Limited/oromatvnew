// What the reader has chosen about comments, kept on this phone only: commenters they hid, comments they reported (so a reported
// comment disappears from their view straight away), and whether they agreed to the Terms of Use before posting.
// Stored with expo-secure-store; in a desktop browser preview, where that is not available, it falls back to localStorage and then to memory.
// Changes are applied one at a time, after the saved values have been read, so two quick taps (or a tap right at start-up) never overwrite each other.
import { useSyncExternalStore } from 'react';
import * as SecureStore from 'expo-secure-store';

export const TERMS_VERSION = '2026-11'; // change it to ask everyone to agree to the terms again

const KEYS = { hidden: 'oroma_hidden_commenters', reported: 'oroma_reported_comments', terms: 'oroma_ugc_terms' };
const MAX_HIDDEN = 40;
const MAX_REPORTED = 100;

const memory = {};
let state = { ready: false, hidden: [], reportedIds: [], termsAccepted: false }; // hidden: [{ key, name }] newest first
let loading = null;
let queue = Promise.resolve(); // changes wait here, one after the other
const listeners = new Set();

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

/** The same key for the same name wherever it is compared: no accents, lower-case, single spaces, at most 40 characters. */
export function nameKey(name) {
  let n = String(name ?? '');
  try {
    n = n.normalize('NFKD').replace(/[̀-ͯ]/g, '');
  } catch {
    // normalize is missing on some engines: use the plain text.
  }
  return n.toLowerCase().replace(/\s+/g, ' ').trim().slice(0, 40);
}

const parse = (text, fallback) => {
  try {
    const value = JSON.parse(text);
    return Array.isArray(value) ? value : fallback;
  } catch {
    return fallback;
  }
};

let snapshot = build();

function build() {
  return {
    ...state,
    hiddenNames: state.hidden.map((h) => h.name),
    isHidden: (name) => state.hidden.some((h) => h.key === nameKey(name)),
    isReported: (id) => state.reportedIds.includes(id),
    hideName,
    unhideName,
    markReported,
    acceptTerms,
  };
}

function emit(patch) {
  state = { ...state, ...patch };
  snapshot = build();
  listeners.forEach((l) => l());
}

function load() {
  if (!loading) {
    loading = (async () => {
      const [hidden, reported, terms] = await Promise.all([readKey(KEYS.hidden), readKey(KEYS.reported), readKey(KEYS.terms)]);
      emit({
        ready: true,
        hidden: parse(hidden, []).filter((h) => h && typeof h.key === 'string').slice(0, MAX_HIDDEN),
        reportedIds: parse(reported, []).filter((id) => Number.isInteger(id)).slice(0, MAX_REPORTED),
        termsAccepted: terms === TERMS_VERSION,
      });
    })().catch(() => emit({ ready: true }));
  }
  return loading;
}

/** Runs a change after the saved values are loaded and after the change before it has been written. */
function change(apply) {
  queue = queue
    .then(load)
    .then(apply)
    .catch(() => {});
  return queue;
}

function hideName(name) {
  const key = nameKey(name);
  if (!key) return Promise.resolve();
  return change(() => {
    if (state.hidden.some((h) => h.key === key)) return undefined;
    const hidden = [{ key, name: String(name).trim().slice(0, 60) }, ...state.hidden].slice(0, MAX_HIDDEN);
    emit({ hidden });
    return writeKey(KEYS.hidden, JSON.stringify(hidden));
  });
}

function unhideName(name) {
  const key = nameKey(name);
  return change(() => {
    const hidden = state.hidden.filter((h) => h.key !== key);
    emit({ hidden });
    return writeKey(KEYS.hidden, JSON.stringify(hidden));
  });
}

function markReported(id) {
  if (!Number.isInteger(id)) return Promise.resolve();
  return change(() => {
    if (state.reportedIds.includes(id)) return undefined;
    const reportedIds = [id, ...state.reportedIds].slice(0, MAX_REPORTED);
    emit({ reportedIds });
    return writeKey(KEYS.reported, JSON.stringify(reportedIds));
  });
}

function acceptTerms() {
  return change(() => {
    if (state.termsAccepted) return undefined;
    emit({ termsAccepted: true });
    return writeKey(KEYS.terms, TERMS_VERSION);
  });
}

function subscribe(listener) {
  listeners.add(listener);
  load();
  return () => listeners.delete(listener);
}

/** `{ ready, hiddenNames, reportedIds, termsAccepted, isHidden(name), isReported(id), hideName, unhideName, markReported, acceptTerms }`; the four changes return a promise that settles once saved. */
export const useModeration = () => useSyncExternalStore(subscribe, () => snapshot, () => snapshot);

load(); // start reading now, so the first story opened already knows who is hidden
