// "Listen to Q FM": one radio player for the whole app. Pressing the button asks the server for the station's live stream
// and what is playing (GET /radio — the server reads it from the Radio Garden / station link set in the admin), plays it,
// keeps playing with the screen locked, shows the song on the lock screen, and refreshes "now playing" while it plays.
// The bar above the tabs, the button on the Live screen and the video player (which is muted while the radio plays) all
// read the same state from here.
import { useSyncExternalStore } from 'react';
import { Platform } from 'react-native';
import { createAudioPlayer, setAudioModeAsync } from 'expo-audio';
import api from './api';

const REFRESH_MS = 20000;

let snapshot = {
  phase: 'idle', // idle | loading | playing | paused
  info: null, // { enabled, name, place, frequency, stream_url, now_playing: { title, artist, art } | null, listeners }
  error: null,
};
const listeners = new Set();
const set = (patch) => {
  snapshot = { ...snapshot, ...patch };
  listeners.forEach((l) => l());
};

/** The radio's state: `{ phase, info, error }`. */
export const useRadio = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot, () => snapshot);

let player = null;
let configured = false;
let refreshTimer = null;
let watchdog = null;
let userStopped = true;

async function configure() {
  if (configured) return;
  configured = true;
  // Play with the silent switch on, and keep playing when the app is in the background or the phone is locked.
  await setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: true, interruptionMode: 'doNotMix' }).catch(() => {});
  player = createAudioPlayer(null, { updateInterval: 1000, keepAudioSessionActive: true });
  player.addListener('playbackStatusUpdate', onStatus);
}

function onStatus(status) {
  if (userStopped) return;
  // The station could not be reached or the stream broke: say so plainly instead of showing a silent "Paused" bar.
  if (status.error) {
    stopRadio();
    set({ error: 'The Q FM stream is not reachable right now. Please try again in a little while.' });
    return;
  }
  if (status.playing) {
    clearTimeout(watchdog);
    if (snapshot.phase !== 'playing') set({ phase: 'playing', error: null });
  } else if (!status.isBuffering && snapshot.phase === 'playing') {
    set({ phase: 'paused' }); // an interruption (a call, another app); pressing play rejoins the live stream
  }
}

function lockScreen(info) {
  if (Platform.OS === 'web' || !player) return;
  const song = info.now_playing;
  try {
    player.setActiveForLockScreen(
      true,
      { title: song?.title || info.name || 'Q FM', artist: song?.artist || 'Oroma TV · live radio', albumTitle: info.name || 'Q FM', artworkUrl: song?.art || undefined },
      { showSeekForward: false, showSeekBackward: false, isLiveStream: true },
    );
  } catch {
    // Lock screen controls are a nicety; the radio still plays without them.
  }
}

/** Fetches the station's details (stream address, now playing). */
export async function loadRadio() {
  const { data } = await api.get('/radio');
  const info = data.data;
  set({ info });
  return info;
}

/** While it plays, keep "now playing" fresh. */
function startRefreshing() {
  clearInterval(refreshTimer);
  refreshTimer = setInterval(() => {
    if (userStopped) return;
    loadRadio()
      .then((info) => lockScreen(info))
      .catch(() => {}); // offline for a moment: the stream keeps playing
  }, REFRESH_MS);
}

/** Joins the live radio and plays. */
export async function playRadio() {
  if (snapshot.phase === 'loading') return;
  userStopped = false;
  set({ phase: 'loading', error: null });
  clearTimeout(watchdog);
  watchdog = setTimeout(() => {
    if (snapshot.phase === 'loading') {
      stopRadio();
      set({ error: 'The radio could not start. Check your connection and try again.' });
    }
  }, 25000);
  try {
    await configure();
    const info = await loadRadio();
    if (!info.enabled || !info.stream_url) throw new Error('quiet');
    // A live stream must join "now", so always load it fresh rather than resuming old buffered sound.
    player.replace({ uri: info.stream_url });
    player.play();
    lockScreen(info);
    startRefreshing();
  } catch (e) {
    userStopped = true;
    clearTimeout(watchdog);
    set({ phase: 'idle', error: e?.response?.status === 404 ? 'The radio is not available right now. Try again soon.' : 'The radio needs an internet connection.' });
  }
}

export function stopRadio() {
  userStopped = true;
  clearTimeout(watchdog);
  clearInterval(refreshTimer);
  try {
    player?.pause();
    if (Platform.OS !== 'web') player?.clearLockScreenControls();
  } catch {
    // Already stopped.
  }
  set({ phase: 'idle' });
}

export const toggleRadio = () => (snapshot.phase === 'idle' || snapshot.phase === 'paused' ? playRadio() : stopRadio());

/** "Artist — Title", or the station's place / frequency when the station does not say what is playing. */
export function nowPlayingText(info) {
  if (!info) return 'Live radio';
  const song = info.now_playing;
  if (song && (song.title || song.artist)) return `${song.artist ? `${song.artist} — ` : ''}${song.title || ''}`;
  const bits = [info.frequency, info.place, info.listeners > 0 ? `${info.listeners} listening` : null].filter(Boolean);
  return bits.length ? bits.join(' · ') : 'Live radio';
}
