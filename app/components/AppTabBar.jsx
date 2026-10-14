// The bottom tab bar: Home, News, a raised round "Live" button in the middle (with a red dot while Oroma TV is on air),
// Shows and More. It draws the tabs the navigator in app/(tabs)/_layout.jsx describes.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SHADOW } from '../constants/theme';
import { useLiveStatus } from '../lib/liveStatus';
import AppText from './AppText';

export default function AppTabBar({ state, descriptors, navigation }) {
  const insets = useSafeAreaInsets();
  const { status } = useLiveStatus();

  return (
    <View style={[styles.bar, { height: 62 + insets.bottom, paddingBottom: insets.bottom }]}>
      {state.routes.map((route, index) => {
        const { options } = descriptors[route.key];
        const focused = state.index === index;
        const label = options.title ?? route.name;
        const isLive = route.name === 'live';
        const color = focused ? COLORS.primary : COLORS.gray500;

        const onPress = () => {
          const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
          if (!focused && !event.defaultPrevented) navigation.navigate(route.name, route.params);
        };

        return (
          <Pressable key={route.key} onPress={onPress} accessibilityRole="button" accessibilityState={{ selected: focused }} accessibilityLabel={label} style={styles.tab}>
            {isLive ? (
              <View style={[styles.liveButton, focused && styles.liveButtonFocused]}>
                <Ionicons name="play" size={24} color={COLORS.white} style={{ marginLeft: 2 }} />
                {status === 'live' ? <View style={styles.liveDot} /> : null}
              </View>
            ) : (
              options.tabBarIcon?.({ focused, color, size: 23 })
            )}
            <AppText style={[styles.label, { color }, isLive && styles.liveLabel]}>{label}</AppText>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', backgroundColor: COLORS.white, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border, paddingTop: 8 },
  tab: { flex: 1, alignItems: 'center', gap: 3 },
  label: { fontFamily: FONTS.semibold, fontSize: 11, lineHeight: 14 },
  liveLabel: { marginTop: -2 },
  liveButton: { width: 54, height: 54, borderRadius: 27, marginTop: -26, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 4, borderColor: COLORS.white, ...SHADOW },
  liveButtonFocused: { backgroundColor: COLORS.primaryDark },
  liveDot: { position: 'absolute', top: -2, right: -2, width: 14, height: 14, borderRadius: 7, backgroundColor: COLORS.live, borderWidth: 2.5, borderColor: COLORS.white },
});
