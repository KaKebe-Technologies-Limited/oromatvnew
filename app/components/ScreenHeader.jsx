// Top bar for pushed screens (article, show, contact, about): a back button, a title, and an optional right-hand action.
import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function ScreenHeader({ title, right, dark = false }) {
  const insets = useSafeAreaInsets();
  const fg = dark ? COLORS.white : COLORS.black;

  const goBack = () => (router.canGoBack() ? router.back() : router.replace('/'));

  return (
    <View style={[styles.bar, { paddingTop: insets.top + 6 }, dark && { backgroundColor: COLORS.ink }]}>
      <Pressable accessibilityRole="button" accessibilityLabel="Go back" onPress={goBack} hitSlop={8} style={styles.back}>
        <Ionicons name="chevron-back" size={24} color={fg} />
      </Pressable>
      <AppText variant="subheading" numberOfLines={1} style={[styles.title, { color: fg }]}>
        {title}
      </AppText>
      <View style={styles.right}>{right}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: SPACING.sm, paddingBottom: 8, backgroundColor: COLORS.white, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  back: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
  title: { flex: 1, textAlign: 'center' },
  right: { minWidth: 40, alignItems: 'flex-end', justifyContent: 'center' },
});
