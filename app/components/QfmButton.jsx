// The small "Listen to Q FM" button. Press it to start the live radio (it reads the station's live stream from the server),
// press again to stop. It shows what it is doing: connecting, playing, or a short message if the station cannot be reached.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { toggleRadio, useRadio } from '../lib/radio';
import AppText from './AppText';

export default function QfmButton({ name = 'Q FM', style }) {
  const { phase, error } = useRadio();
  const on = phase === 'playing';
  const loading = phase === 'loading';

  return (
    <View style={style}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={on ? `Stop ${name}` : `Listen to ${name}`}
        accessibilityState={{ busy: loading }}
        onPress={toggleRadio}
        style={({ pressed }) => [styles.pill, on && styles.pillOn, pressed && { opacity: 0.85 }]}
      >
        {loading ? <ActivityIndicator size="small" color={COLORS.white} /> : <Ionicons name={on ? 'stop-circle' : 'radio'} size={17} color={on ? COLORS.ink : COLORS.white} />}
        <AppText style={[styles.text, on && { color: COLORS.ink }]}>{on ? `${name} is playing` : loading ? 'Connecting…' : `Listen to ${name}`}</AppText>
      </Pressable>
      {error && phase === 'idle' ? <AppText variant="caption" style={styles.error}>{error}</AppText> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  pill: { flexDirection: 'row', alignItems: 'center', gap: 8, alignSelf: 'flex-start', minHeight: 38, paddingHorizontal: 14, borderRadius: RADIUS.full, backgroundColor: 'rgba(255,255,255,0.14)', borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.4)' },
  pillOn: { backgroundColor: COLORS.gold, borderColor: COLORS.gold },
  text: { color: COLORS.white, fontFamily: FONTS.semibold, fontSize: 13 },
  error: { color: '#FCA5A5', marginTop: 6 },
});
