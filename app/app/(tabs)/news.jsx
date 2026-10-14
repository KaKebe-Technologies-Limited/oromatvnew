// News tab: search, category chips and an endless list of stories (the newest one big, the rest as compact rows).
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, TextInput, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import ArticleCard from '../../components/ArticleCard';
import Chip from '../../components/Chip';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { categoryIcon } from '../../lib/categories';
import { useFetch } from '../../lib/useFetch';
import { usePaged } from '../../lib/usePaged';

/** Returns `value` only after it has stopped changing for `delay` ms (so we do not search on every keystroke). */
function useDebounced(value, delay = 400) {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}

export default function NewsScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams();
  const input = useRef(null);
  const [text, setText] = useState('');
  const [category, setCategory] = useState('');
  const query = useDebounced(text.trim());

  // Arriving from a category chip or the search button on Home.
  useEffect(() => {
    if (typeof params.category === 'string') setCategory(params.category);
  }, [params.category]);
  useEffect(() => {
    if (params.focus) setTimeout(() => input.current?.focus(), 250);
  }, [params.focus]);

  const categories = useFetch('/categories');
  const list = usePaged('/articles', { per_page: 12, q: query || undefined, category: category || undefined });

  const header = (
    <View style={[styles.header, { paddingTop: insets.top + 10 }]}>
      <AppText variant="title" style={{ marginBottom: SPACING.sm + 4 }}>
        News
      </AppText>
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput
          ref={input}
          value={text}
          onChangeText={setText}
          placeholder="Search stories…"
          placeholderTextColor={COLORS.gray500}
          returnKeyType="search"
          autoCorrect={false}
          maxFontSizeMultiplier={1.15}
          style={styles.searchInput}
        />
        {text ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Clear search" onPress={() => setText('')} hitSlop={8}>
            <Ionicons name="close-circle" size={18} color={COLORS.gray500} />
          </Pressable>
        ) : null}
      </View>
      <FlatList
        horizontal
        data={[{ id: 'all', name: 'All', slug: '', icon: 'fa-newspaper', color: COLORS.primary }, ...(categories.data?.data ?? [])]}
        keyExtractor={(c) => String(c.id)}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ gap: 8, paddingTop: SPACING.sm + 4 }}
        renderItem={({ item }) => <Chip label={item.name} icon={categoryIcon(item.icon)} color={item.color} selected={category === item.slug} onPress={() => setCategory(item.slug)} />}
      />
    </View>
  );

  return (
    <View style={styles.screen}>
      {header}
      {list.loading && list.items.length === 0 ? (
        <StateView loading />
      ) : list.error && list.items.length === 0 ? (
        <StateView error={list.error} onRetry={list.retry} />
      ) : (
        <FlatList
          data={list.items}
          keyExtractor={(a) => String(a.id)}
          contentContainerStyle={styles.list}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.6}
          refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          ListHeaderComponent={
            list.items.length ? (
              <AppText variant="caption" style={{ marginBottom: SPACING.sm + 4 }}>
                {list.total} {list.total === 1 ? 'story' : 'stories'}
                {query ? ` for “${query}”` : ''}
              </AppText>
            ) : null
          }
          ListEmptyComponent={<StateView empty emptyIcon="newspaper-outline" emptyTitle="No stories found" emptyMessage={query ? 'Try a different word, or clear the search.' : 'Nothing has been published here yet.'} />}
          ListFooterComponent={list.loadingMore ? <ActivityIndicator color={COLORS.primary} style={{ marginVertical: SPACING.lg }} /> : <View style={{ height: SPACING.md }} />}
          renderItem={({ item, index }) =>
            index === 0 ? (
              <ArticleCard article={item} variant="feature" style={{ marginBottom: SPACING.sm }} />
            ) : (
              <View style={styles.rowWrap}>
                <ArticleCard article={item} variant="row" />
              </View>
            )
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  header: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.sm + 4, backgroundColor: COLORS.white, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  search: { flexDirection: 'row', alignItems: 'center', gap: 10, height: 46, paddingHorizontal: 14, borderRadius: RADIUS.full, backgroundColor: COLORS.gray100 },
  searchInput: { flex: 1, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black, paddingVertical: 0 },
  list: { padding: SPACING.md, flexGrow: 1 },
  rowWrap: { paddingVertical: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
});
