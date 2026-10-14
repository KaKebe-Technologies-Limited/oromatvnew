// Opening links and sharing: web pages in an in-app browser, other apps (YouTube, phone, mail) through the system,
// and the share sheet. Also the official social profiles and contact details (same as the website's Contact page).
import { Linking, Platform, Share } from 'react-native';
import * as WebBrowser from 'expo-web-browser';

export const CONTACT = {
  email: 'info@oromatv.com',
  tips: 'tips@oromatv.com',
  phone: '+256 772 033 333',
  phoneLink: 'tel:+256772033333',
  address: 'Oyam Road, Lira City, Uganda',
  hours: 'Monday – Friday, 9:00 AM – 5:00 PM (EAT)',
};

export const SOCIALS = [
  { key: 'youtube', label: 'YouTube', icon: 'logo-youtube', color: '#E11D1D', url: 'https://www.youtube.com/@oromatv' },
  { key: 'facebook', label: 'Facebook', icon: 'logo-facebook', color: '#1877F2', url: 'https://www.facebook.com/oromatv' },
  { key: 'instagram', label: 'Instagram', icon: 'logo-instagram', color: '#C13584', url: 'https://www.instagram.com/oromatv' },
  { key: 'twitter', label: 'X / Twitter', icon: 'logo-twitter', color: '#111827', url: 'https://twitter.com/oromatv' },
  { key: 'telegram', label: 'Telegram', icon: 'paper-plane', color: '#229ED9', url: 'https://t.me/oromatv' },
];

/** A web page, shown in the in-app browser (falls back to the system browser). */
export async function openLink(url) {
  if (!url) return;
  try {
    await WebBrowser.openBrowserAsync(url);
  } catch {
    Linking.openURL(url).catch(() => {});
  }
}

/** Hand a link to the system: the YouTube app, the dialer, the mail app… */
export function openExternal(url) {
  if (!url) return;
  Linking.openURL(url).catch(() => {});
}

/** Opens the share sheet with a title and link. On a desktop browser (no share sheet) it copies the link instead. */
export async function shareLink({ title, url }) {
  try {
    if (Platform.OS === 'web') {
      if (globalThis.navigator?.share) await globalThis.navigator.share({ title, url });
      else if (globalThis.navigator?.clipboard) await globalThis.navigator.clipboard.writeText(url);
      return;
    }
    await Share.share(Platform.OS === 'ios' ? { title, message: title, url } : { title, message: `${title}\n${url}` });
  } catch {
    // The person closed the share sheet — nothing to do.
  }
}
