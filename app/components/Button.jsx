// Brand button: filled maroon (primary), outlined, white ("light", for dark backgrounds), dark or ghost,
// with an optional icon and a built-in loading spinner.
import { ActivityIndicator, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';

const LOOK = {
  primary: { bg: COLORS.primary, fg: COLORS.white, border: COLORS.primary },
  outline: { bg: 'transparent', fg: COLORS.primary, border: COLORS.primary },
  light: { bg: COLORS.white, fg: COLORS.primary, border: COLORS.white },
  dark: { bg: COLORS.ink, fg: COLORS.white, border: COLORS.ink },
  ghost: { bg: 'rgba(255,255,255,0.14)', fg: COLORS.white, border: 'rgba(255,255,255,0.4)' },
};

export default function Button({ title, onPress, variant = 'primary', icon, loading = false, disabled = false, small = false, style }) {
  const look = LOOK[variant] ?? LOOK.primary;
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [
        styles.base,
        small && styles.small,
        { backgroundColor: look.bg, borderColor: look.border },
        inactive && styles.inactive,
        pressed && { opacity: 0.85 },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={look.fg} />
      ) : (
        <>
          {icon ? <Ionicons name={icon} size={small ? 16 : 18} color={look.fg} /> : null}
          <AppText style={{ fontFamily: FONTS.semibold, fontSize: small ? 13 : 15, color: look.fg }}>{title}</AppText>
        </>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: { minHeight: 50, paddingHorizontal: SPACING.lg, borderRadius: RADIUS.full, borderWidth: 1.5, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center' },
  small: { minHeight: 38, paddingHorizontal: SPACING.md },
  inactive: { opacity: 0.55 },
});
