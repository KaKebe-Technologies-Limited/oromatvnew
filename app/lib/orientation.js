// Turning the phone sideways for fullscreen video, and back. The app itself stays upright (portrait) everywhere;
// only a video in fullscreen is allowed to go landscape. Does nothing in a desktop browser.
import { Platform } from 'react-native';
import * as ScreenOrientation from 'expo-screen-orientation';

const run = async (lock) => {
  if (Platform.OS === 'web') return;
  try {
    await ScreenOrientation.lockAsync(lock);
  } catch {
    // Some devices refuse; the video still plays, it just does not rotate.
  }
};

/** Upright: used when the app starts and when a video leaves fullscreen. */
export const lockPortrait = () => run(ScreenOrientation.OrientationLock.PORTRAIT_UP);

/** Sideways (either landscape direction, following how the phone is held): used for fullscreen video. */
export const lockLandscape = () => run(ScreenOrientation.OrientationLock.LANDSCAPE);
