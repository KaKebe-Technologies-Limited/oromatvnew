// The red "LIVE" pill with a pulsing dot (or a grey "OFFLINE" pill when nothing is on air).
import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, View } from 'react-native';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import AppText from './AppText';

export default function LiveBadge({ status = 'offline', style }) {
  const pulse = useRef(new Animated.Value(1)).current;
  const live = status === 'live';

  useEffect(() => {
    if (!live) return undefined;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 0.25, duration: 700, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 1, duration: 700, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [live, pulse]);

  return (
    <View style={[styles.pill, { backgroundColor: live ? COLORS.live : 'rgba(17,24,39,0.72)' }, style]}>
      <Animated.View style={[styles.dot, { opacity: live ? pulse : 0.6 }]} />
      <AppText style={styles.text}>{live ? 'LIVE' : 'OFFLINE'}</AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 10, height: 24, borderRadius: RADIUS.full, alignSelf: 'flex-start' },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: COLORS.white },
  text: { fontFamily: FONTS.bold, fontSize: 11, lineHeight: 14, letterSpacing: 0.8, color: COLORS.white },
});
