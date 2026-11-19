// Push notifications for the updates a person can opt in to in "My profile": asking the system permission (only when they switch
// notifications on, never at launch), getting this phone's Expo push token, and opening the right screen when a notification is tapped.
// expo-notifications is loaded only where it can work. A desktop browser preview and Expo Go (which has no push for our app) never load
// it, so they neither crash nor show its warnings; every function here is safe to call there and never throws.
import { useEffect } from 'react';
import { Linking, Platform } from 'react-native';
import { isRunningInExpoGo } from 'expo';
import Constants from 'expo-constants';
import { router } from 'expo-router';
import { openLink } from './links';

/** The Android channel every update is sent to (the server sends channelId "updates"; app.json sets it as the default too). */
export const CHANNEL_ID = 'updates';

const TOKEN_TIMEOUT_MS = 20000;
const SLUG = /^[A-Za-z0-9_-]{1,255}$/;
const ID = /^\d{1,10}$/;
// Only our own website may be opened from a notification: https, the host oromatv.com (or www.), nothing like user@host or a port.
const OWN_SITE = /^https:\/\/(?:www\.)?oromatv\.com(?:[/?#][^\s]*)?$/i;

let loaded; // undefined = not tried yet, null = not available here
function native() {
  if (loaded !== undefined) return loaded;
  loaded = null;
  if (Platform.OS === 'web' || isRunningInExpoGo()) return loaded;
  try {
    loaded = require('expo-notifications');
  } catch {
    loaded = null;
  }
  return loaded;
}

const fail = (reason, message) => ({ ok: false, reason, message });

function describeFailure(error) {
  const code = String(error?.code || '');
  const text = String(error?.message || error || '');
  if (Platform.OS === 'ios' && /simulator/i.test(text)) {
    return fail('simulator', 'Notifications do not work on the iPhone simulator. Please try on a real phone.');
  }
  if (code === 'ERR_NOTIFICATIONS_NETWORK_ERROR' || code === 'TIMEOUT') {
    return fail('error', 'We could not reach the notification service. Please check your connection and try again.');
  }
  // On Android this is what a build without Firebase (google-services.json), or a phone without Google Play services, answers.
  if (code === 'E_REGISTRATION_FAILED' || /firebase|fcm|google|play services|SERVICE_NOT_AVAILABLE/i.test(text)) {
    return fail('unavailable', 'Notifications are not available on this phone right now. You can still get updates by email.');
  }
  return fail('error', 'We could not switch notifications on. Please try again in a moment.');
}

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(Object.assign(new Error('Timed out'), { code: 'TIMEOUT' })), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

let enabling = null;

async function enable(N) {
  // Android 13+ shows no permission question until a channel exists, so the channel comes first.
  if (Platform.OS === 'android') {
    await N.setNotificationChannelAsync(CHANNEL_ID, {
      name: 'Oroma TV updates',
      description: 'News, live broadcasts and new shows you asked to hear about',
      importance: N.AndroidImportance.HIGH,
      sound: 'default',
      lightColor: '#800000',
      showBadge: false,
    });
  }

  let permission = await N.getPermissionsAsync();
  if (!permission.granted) {
    // Denied and the system will not ask again (iPhone after one "Don't Allow", Android after two): only Settings can change it.
    if (permission.canAskAgain === false) {
      return fail('denied', 'Notifications are switched off for Oroma TV on this phone. You can switch them on in Settings.');
    }
    permission = await N.requestPermissionsAsync({ ios: { allowAlert: true, allowSound: true, allowBadge: false } });
  }
  if (!permission.granted) {
    return fail('denied', 'Notifications are switched off for Oroma TV on this phone. You can switch them on in Settings.');
  }

  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  if (!projectId) return fail('unavailable', 'Notifications are not available in this version of the app.');

  const { data: token } = await withTimeout(N.getExpoPushTokenAsync({ projectId }), TOKEN_TIMEOUT_MS);
  if (typeof token !== 'string' || !token) return fail('error', 'We could not switch notifications on. Please try again in a moment.');
  return { ok: true, token, platform: Platform.OS === 'ios' ? 'ios' : 'android' };
}

/**
 * Asks the system for permission to show notifications (the only place that does, called when the person switches the toggle on)
 * and gets this phone's Expo push token. Never throws.
 * Resolves { ok: true, token, platform } or { ok: false, reason: 'denied' | 'unavailable' | 'simulator' | 'error', message } where
 * the message is friendly and can be shown as it is.
 */
export function enablePushNotifications() {
  const N = native();
  if (!N) return Promise.resolve(fail('unavailable', 'Notifications are only available in the installed Oroma TV app.'));
  if (!enabling) {
    enabling = enable(N)
      .catch(describeFailure)
      .finally(() => {
        enabling = null;
      });
  }
  return enabling;
}

/** Best effort: forgets this phone's push registration. The caller also tells the server to drop the token. */
export async function disablePushNotifications() {
  const N = native();
  if (!N) return;
  try {
    await N.unregisterForNotificationsAsync();
  } catch {
    // Nothing to undo (never registered, or not available): the server no longer sends to this phone anyway.
  }
}

/** What the system currently says: 'granted', 'denied' or 'undetermined' (never asked yet, or not available here). */
export async function getNotificationPermission() {
  const N = native();
  if (!N) return 'undetermined';
  try {
    const permission = await N.getPermissionsAsync();
    if (permission.granted) return 'granted';
    return permission.status === 'denied' ? 'denied' : 'undetermined';
  } catch {
    return 'undetermined';
  }
}

/** Opens this app's page in the phone's Settings (where notifications can be switched back on). */
export function openSystemSettings() {
  try {
    Promise.resolve(Linking.openSettings()).catch(() => {});
  } catch {
    // Not available on this platform.
  }
}

/**
 * Turns the data sent with a notification into where to go, or null for anything we do not recognise:
 * { route: { pathname, params? }, tab: bool } for a screen of the app, { url } for a page of our own website.
 */
export function notificationTarget(data) {
  let d = data;
  if (typeof d === 'string') {
    try {
      d = JSON.parse(d);
    } catch {
      return null;
    }
  }
  if (!d || typeof d !== 'object') return null;
  switch (d.type) {
    case 'home':
      return { route: { pathname: '/' }, tab: true };
    case 'live':
      return { route: { pathname: '/live' }, tab: true };
    case 'article':
      return typeof d.slug === 'string' && SLUG.test(d.slug) ? { route: { pathname: '/article/[slug]', params: { slug: d.slug } } } : null;
    case 'show':
    case 'premiere': {
      const id = String(d.id ?? '');
      return ID.test(id) ? { route: { pathname: d.type === 'show' ? '/show/[id]' : '/premiere/[id]', params: { id } } } : null;
    }
    case 'url':
      return typeof d.url === 'string' && d.url.length <= 500 && OWN_SITE.test(d.url) ? { url: d.url } : null;
    default:
      return null;
  }
}

function open(data) {
  const target = notificationTarget(data);
  if (!target) return;
  try {
    if (target.url) openLink(target.url);
    else if (target.tab) router.navigate(target.route);
    else router.push(target.route);
  } catch {
    // The screen is not ready or the route is unknown: staying where we are is fine.
  }
}

const handled = []; // identifiers of taps already opened, so the same tap is never acted on twice

function onResponse(N, response) {
  try {
    if (!response || response.actionIdentifier !== N.DEFAULT_ACTION_IDENTIFIER) return;
    const id = response.notification?.request?.identifier;
    if (id) {
      if (handled.includes(id)) return;
      handled.push(id);
      if (handled.length > 20) handled.shift();
    }
    open(response.notification?.request?.content?.data);
    N.clearLastNotificationResponse();
  } catch {
    // A notification we cannot read is ignored.
  }
}

/**
 * Used once by the root layout. Shows notifications as a banner with sound (no badge) while the app is open, and opens the right
 * screen when one is tapped, including the tap that started the app. `ready` is false until the navigator is on screen, because a
 * screen cannot be opened before that; it never asks for any permission.
 */
export function usePushTapHandler(ready = true) {
  useEffect(() => {
    const N = native();
    if (!N) return;
    try {
      N.setNotificationHandler({
        handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
      });
    } catch {
      // Not available here.
    }
  }, []);

  useEffect(() => {
    const N = ready ? native() : null;
    if (!N) return undefined;
    let subscription = null;
    try {
      subscription = N.addNotificationResponseReceivedListener((response) => onResponse(N, response));
      onResponse(N, N.getLastNotificationResponse());
    } catch {
      // Not available here.
    }
    return () => {
      try {
        subscription?.remove();
      } catch {
        // Already removed.
      }
    };
  }, [ready]);
}
