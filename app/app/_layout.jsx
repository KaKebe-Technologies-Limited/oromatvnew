// Root layout: loads the website's fonts (Montserrat headings, Inter body), keeps the splash screen up until they are
// ready, and defines the top-level navigation stack (the tab bar, then pushed screens like a story or a show).
import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useFonts } from 'expo-font';
// Each weight is imported from its own file: importing the package root would bundle all 36 font files (about 16 MB) for the 6 we use.
import { Inter_400Regular } from '@expo-google-fonts/inter/400Regular';
import { Inter_500Medium } from '@expo-google-fonts/inter/500Medium';
import { Inter_600SemiBold } from '@expo-google-fonts/inter/600SemiBold';
import { Inter_700Bold } from '@expo-google-fonts/inter/700Bold';
import { Montserrat_700Bold } from '@expo-google-fonts/montserrat/700Bold';
import { Montserrat_800ExtraBold } from '@expo-google-fonts/montserrat/800ExtraBold';
import { setAudioModeAsync } from 'expo-audio';
import StateView from '../components/StateView';
import { COLORS } from '../constants/theme';
import { lockPortrait } from '../lib/orientation';

SplashScreen.preventAutoHideAsync().catch(() => {});

// If a screen ever fails while drawing, show a friendly retry instead of closing the app.
export function ErrorBoundary({ retry }) {
  // If the layout itself failed, its effect never hid the splash screen and this screen would stay behind it forever.
  useEffect(() => {
    SplashScreen.hideAsync().catch(() => {});
  }, []);
  return <StateView error="Something went wrong. Please try again." onRetry={retry} />;
}

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    Inter_400Regular,
    Inter_500Medium,
    Inter_600SemiBold,
    Inter_700Bold,
    Montserrat_700Bold,
    Montserrat_800ExtraBold,
  });

  useEffect(() => {
    if (fontsLoaded || fontError) SplashScreen.hideAsync().catch(() => {});
  }, [fontsLoaded, fontError]);

  // The app stays upright; only a video in fullscreen turns the phone sideways (see components/YouTubePlayer.jsx).
  useEffect(() => {
    lockPortrait();
    // On an iPhone the silent switch would otherwise mute the video's sound.
    setAudioModeAsync({ playsInSilentMode: true }).catch(() => {});
  }, []);

  // If the fonts fail to load the app still opens (with the system font) rather than staying on the splash screen.
  if (!fontsLoaded && !fontError) return null;

  return (
    <>
      <StatusBar style="dark" />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: COLORS.canvas } }} />
    </>
  );
}
