// Root layout: loads the website's fonts (Montserrat headings, Inter body), keeps the splash screen up until they are
// ready, and defines the top-level navigation stack (the tab bar, then pushed screens like a story or a show).
import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useFonts } from 'expo-font';
import { Inter_400Regular, Inter_500Medium, Inter_600SemiBold, Inter_700Bold } from '@expo-google-fonts/inter';
import { Montserrat_700Bold, Montserrat_800ExtraBold } from '@expo-google-fonts/montserrat';
import { COLORS } from '../constants/theme';
import { lockPortrait } from '../lib/orientation';

SplashScreen.preventAutoHideAsync().catch(() => {});

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
