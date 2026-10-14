// A section title with an optional "See all" action, used between blocks on the home screen.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function SectionHeader({ title, actionLabel = 'See all', onAction, light = false, style }) {
  return (
    <View style={[styles.row, style]}>
      <View style={styles.titleWrap}>
        <AppText variant="heading" style={light ? { color: COLORS.white } : undefined}>
          {title}
        </AppText>
        <View style={styles.dot} />
      </View>
      {onAction ? (
        <Pressable accessibilityRole="button" onPress={onAction} hitSlop={8} style={styles.action}>
          <AppText variant="label" style={{ color: COLORS.primary }}>
            {actionLabel}
          </AppText>
          <Ionicons name="arrow-forward" size={14} color={COLORS.primary} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md, marginBottom: SPACING.sm + 4 },
  titleWrap: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: COLORS.primary },
  action: { flexDirection: 'row', alignItems: 'center', gap: 4 },
});
