// A rounded filter chip (category filters, subject pickers). Filled when selected; `color` tints it per category.
import { Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import AppText from './AppText';

export default function Chip({ label, selected = false, onPress, icon, color = COLORS.primary, style }) {
  const fg = selected ? COLORS.white : COLORS.gray900;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected }}
      onPress={onPress}
      style={({ pressed }) => [styles.chip, selected && { backgroundColor: color, borderColor: color }, pressed && { opacity: 0.85 }, style]}
    >
      {icon ? <Ionicons name={icon} size={14} color={selected ? COLORS.white : color} /> : null}
      <AppText style={{ fontFamily: FONTS.semibold, fontSize: 13, color: fg }}>{label}</AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    height: 36,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.white,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
});
