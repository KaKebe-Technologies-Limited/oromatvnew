// Counting who is watching. While the Live tab is open the app checks in with the server every 30 seconds
// (POST /live/ping) using a random id the phone made up the first time — nothing about the person — so the admin can see
// how many people are watching live right now. The answer also tells the screen how many are watching, for the "N watching" chip.
import { useEffect, useState } from 'react';
import { AppState } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import api from './api';

const KEY = 'oroma_viewer_id';
const BEAT_MS = 30000;
let cachedId = null;

function randomId() {
  const letters = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let id = 'app-';
  for (let i = 0; i < 20; i++) id += letters.charAt(Math.floor(Math.random() * letters.length));
  return id;
}

/** This phone's anonymous viewer id (made once, then remembered; a new one each launch if storage is not available). */
async function viewerId() {
  if (cachedId) return cachedId;
  try {
    const saved = await SecureStore.getItemAsync(KEY);
    if (saved) return (cachedId = saved);
  } catch {
    // Storage is not available (for example in a desktop browser preview): a fresh id for this launch is fine.
  }
  cachedId = randomId();
  try {
    await SecureStore.setItemAsync(KEY, cachedId);
  } catch {
    // Not remembered; harmless.
  }
  return cachedId;
}

/** While `active`, checks in every 30 s. Returns how many devices are watching live (null until the first answer). */
export function usePresence(active) {
  const [watching, setWatching] = useState(null);

  useEffect(() => {
    if (!active) return undefined;
    let cancelled = false;
    const beat = async () => {
      if (AppState.currentState !== 'active') return;
      try {
        const { data } = await api.post('/live/ping', { viewer_id: await viewerId(), platform: 'app' });
        if (!cancelled && typeof data?.data?.watching === 'number') setWatching(data.data.watching);
      } catch {
        // A missed check-in is harmless: the next one counts again.
      }
    };
    beat();
    const timer = setInterval(beat, BEAT_MS);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [active]);

  return watching;
}
