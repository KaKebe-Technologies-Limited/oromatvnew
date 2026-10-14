// The big "Watch live" card at the top of the home screen. Red-hot maroon while Oroma TV is on air (naming the programme),
// calm dark navy when it is not — then it tells you what is coming next and how long to wait. Tapping goes to the Live tab.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { countdown, formatWhen } from '../lib/format';
import { useLiveStatus } from '../lib/liveStatus';
import AppText from './AppText';
import LiveBadge from './LiveBadge';

export default function LiveCard() {
  const { status, title, next, skew } = useLiveStatus();
  const live = status === 'live';

  // Keep "in 2h 15m" fresh.
  const [, setTick] = useState(0);
  useEffect(() => {
    if (!next) return undefined;
    const timer = setInterval(() => setTick((n) => n + 1), 15000);
    return () => clearInterval(timer);
  }, [next]);

  const heading = live ? (title && title !== 'Oroma TV Live' ? title : 'Oroma TV is live') : next ? `Next live: ${next.title}` : 'Oroma TV Live';
  const sub = live
    ? 'Tap to watch the broadcast now.'
    : next
      ? `${formatWhen(next.starts_at, skew)} · ${countdown(next.starts_at, skew)}`
      : status === 'unknown'
        ? 'Checking the broadcast…'
        : 'Not on air right now — catch up on previous shows.';

  return (
    <Pressable accessibilityRole="button" accessibilityLabel="Watch Oroma TV" onPress={() => router.push('/live')} style={({ pressed }) => [pressed && { opacity: 0.93 }]}>
      <LinearGradient colors={live ? [COLORS.primaryDark, COLORS.primary, '#A8261C'] : [COLORS.ink, '#1B2438']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={styles.glow} />
        <View style={styles.left}>
          <LiveBadge status={live ? 'live' : 'offline'} />
          <AppText variant="title" numberOfLines={2} style={styles.title}>
            {heading}
          </AppText>
          <AppText style={styles.sub}>{sub}</AppText>
        </View>
        <View style={styles.play}>
          <Ionicons name={live ? 'play' : next ? 'calendar' : 'play'} size={26} color={live ? COLORS.primary : COLORS.ink} style={live || !next ? { marginLeft: 3 } : undefined} />
        </View>
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { marginHorizontal: SPACING.md, borderRadius: RADIUS.lg, padding: SPACING.md + 4, flexDirection: 'row', alignItems: 'center', gap: 14, overflow: 'hidden', ...SHADOW },
  glow: { position: 'absolute', right: -60, top: -80, width: 220, height: 220, borderRadius: 110, backgroundColor: 'rgba(226,196,110,0.18)' },
  left: { flex: 1, gap: 6 },
  title: { color: COLORS.white, fontSize: 20, lineHeight: 26 },
  sub: { color: 'rgba(255,255,255,0.8)', fontSize: 13.5, lineHeight: 19 },
  play: { width: 58, height: 58, borderRadius: 29, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
});
