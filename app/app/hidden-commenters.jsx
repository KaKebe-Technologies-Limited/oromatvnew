// Hidden commenters: the names whose comments this reader chose to hide (kept on this phone only), each with a button to show them again.
import { FlatList, StyleSheet, View } from 'react-native';
import AppText from '../components/AppText';
import Button from '../components/Button';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, RADIUS, SPACING } from '../constants/theme';
import { useModeration } from '../lib/moderation';

export default function HiddenCommentersScreen() {
  const mod = useModeration();

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Hidden commenters" />
      <StateView loading={!mod.ready} empty={mod.ready && mod.hiddenNames.length === 0} emptyIcon="eye-outline" emptyTitle="No hidden commenters" emptyMessage="When you hide a commenter from a story, they appear here so you can undo it.">
        <FlatList
          data={mod.hiddenNames}
          keyExtractor={(name) => name}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          ListHeaderComponent={<AppText variant="caption" style={{ marginBottom: SPACING.sm }}>Comments from these names are hidden on this phone only.</AppText>}
          renderItem={({ item }) => (
            <View style={styles.row}>
              <AppText variant="label" style={{ flex: 1 }} numberOfLines={1}>
                {item}
              </AppText>
              <Button title="Show again" small variant="outline" onPress={() => mod.unhideName(item)} />
            </View>
          )}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.md, borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border },
});
