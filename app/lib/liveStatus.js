// Whether Oroma TV is on air right now, and what is coming next. One small shared store (like a tiny global state) that
// polls GET /live once a minute while any screen is watching it — and wakes up again at the exact second the next
// scheduled programme starts — so the tab bar's LIVE dot, the home card and the Live screen always agree.
import { useSyncExternalStore } from 'react';
import { AppState } from 'react-native';
import api from './api';

// status: unknown | live | offline · title: what is on air · next: the next scheduled programme · skew: server clock minus phone clock (ms)
let snapshot = { status: 'unknown', title: null, next: null, skew: 0 };
const listeners = new Set();
let pollTimer = null;
let startTimer = null;

const sameNext = (a, b) => (a?.id ?? null) === (b?.id ?? null) && (a?.starts_at ?? null) === (b?.starts_at ?? null) && (a?.title ?? null) === (b?.title ?? null);

/**
 * Tell the store what the server said. Accepts the "live" block of /home ({ status, title, next, server_time })
 * or the same fields built from /live. Safe to call from any screen that has just fetched them.
 */
export function setLiveInfo(info) {
  if (!info) return;
  const status = info.status === 'live' ? 'live' : 'offline';
  const skew = info.server_time ? Date.parse(info.server_time) - Date.now() : snapshot.skew;
  const next = info.next ?? null;
  const title = info.title ?? null;

  scheduleStartCheck(next, skew);
  if (snapshot.status === status && snapshot.title === title && sameNext(snapshot.next, next) && Math.abs(snapshot.skew - skew) < 2000) return;
  snapshot = { status, title, next, skew };
  listeners.forEach((l) => l());
}

/** Wake up just after the next programme's start time and ask the server what is on air. */
function scheduleStartCheck(next, skew) {
  clearTimeout(startTimer);
  startTimer = null;
  if (!next?.starts_at || listeners.size === 0) return;
  const wait = Date.parse(next.starts_at) - (Date.now() + skew);
  if (wait > 24 * 3600 * 1000) return; // far away: the minute-by-minute check will find it
  startTimer = setTimeout(refreshLiveStatus, Math.max(0, wait) + 1500);
}

/** Re-checks now. Safe to call from anywhere; a failed check keeps the last known state. */
export async function refreshLiveStatus() {
  try {
    const { data } = await api.get('/live');
    const live = data?.data;
    if (live) setLiveInfo({ status: live.status, title: live.stage?.title, next: live.next, server_time: live.server_time });
  } catch {
    // Offline for a moment: keep what we knew.
  }
}

function start() {
  refreshLiveStatus();
  pollTimer = setInterval(() => {
    if (AppState.currentState === 'active') refreshLiveStatus();
  }, 60000);
}

function subscribe(listener) {
  listeners.add(listener);
  if (listeners.size === 1) start();
  return () => {
    listeners.delete(listener);
    if (listeners.size === 0) {
      clearInterval(pollTimer);
      clearTimeout(startTimer);
      pollTimer = null;
      startTimer = null;
    }
  };
}

/** `{ status, title, next, skew }` — status is 'live', 'offline', or 'unknown' until the first answer arrives. */
export const useLiveStatus = () => useSyncExternalStore(subscribe, () => snapshot, () => snapshot);
