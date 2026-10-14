// The radio bar that sits just above the tab bar while Q FM is on: the station, what is playing right now, and a stop button.
// It disappears when the radio is stopped.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS } from '../constants/theme';
import { nowPlayingText, stopRadio, toggleRadio, useRadio } from '../lib/radio';
import AppText from './AppText';

export default function RadioMiniPlayer() {
  const { phase, info } = useRadio();
  if (phase === 'idle') return null;
  const loading = phase === 'loading';
  const playing = phase === 'playing';

  return (
    <View style={styles.bar} accessibilityRole="toolbar" accessibilityLabel="Q FM radio">
      <View style={styles.icon}>{loading ? <ActivityIndicator size="small" color={COLORS.ink} /> : <Ionicons name="radio" size={20} color={COLORS.ink} />}</View>
      <View style={{ flex: 1 }}>
        <View style={styles.nameRow}>
          <View style={styles.livePill}>
            <AppText style={styles.liveText}>LIVE</AppText>
          </View>
          <AppText style={styles.name} numberOfLines={1}>
            {info?.name ?? 'Q FM'}
          </AppText>
        </View>
        <AppText style={styles.now} numberOfLines={1}>
          {loading ? 'Connecting…' : playing ? nowPlayingText(info) : 'Paused'}
        </AppText>
      </View>
      {phase === 'paused' ? (
        <Pressable accessibilityRole="button" accessibilityLabel="Play the radio" onPress={toggleRadio} hitSlop={8} style={styles.action}>
          <Ionicons name="play" size={22} color={COLORS.white} />
        </Pressable>
      ) : null}
      <Pressable accessibilityRole="button" accessibilityLabel="Stop the radio" onPress={stopRadio} hitSlop={8} style={styles.action}>
        <Ionicons name="close" size={22} color={COLORS.white} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, paddingVertical: 10, backgroundColor: '#1B1B24', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.12)' },
  icon: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.gold, alignItems: 'center', justifyContent: 'center' },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  livePill: { backgroundColor: COLORS.live, borderRadius: 5, paddingHorizontal: 6, paddingVertical: 1 },
  liveText: { color: COLORS.white, fontSize: 9.5, lineHeight: 14, fontWeight: '800', letterSpacing: 0.6 },
  name: { color: COLORS.white, fontFamily: FONTS.bold, fontSize: 14.5, flexShrink: 1 },
  now: { color: 'rgba(255,255,255,0.72)', fontSize: 12.5, lineHeight: 17, marginTop: 1 },
  action: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.12)', alignItems: 'center', justifyContent: 'center' },
});
