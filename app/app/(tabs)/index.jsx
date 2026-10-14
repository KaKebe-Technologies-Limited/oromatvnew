// Home tab: the live card, featured-stories carousel, category chips, latest news, trending stories and previous shows.
import { useEffect } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import ArticleCard from '../../components/ArticleCard';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import HeroCarousel from '../../components/HeroCarousel';
import LiveCard from '../../components/LiveCard';
import Logo from '../../components/Logo';
import SectionHeader from '../../components/SectionHeader';
import ShowCard from '../../components/ShowCard';
import StateView from '../../components/StateView';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { categoryIcon } from '../../lib/categories';
import { setLiveInfo } from '../../lib/liveStatus';
import { useFetch } from '../../lib/useFetch';

export default function HomeScreen() {
  const insets = useSafeAreaInsets();
  const { data, loading, refreshing, error, retry, reload } = useFetch('/home');
  const home = data?.data;

  // The home response already says whether Oroma TV is on air, so the tab bar and the live card can update from it.
  useEffect(() => {
    if (home?.live) setLiveInfo(home.live);
  }, [home]);

  return (
    <View style={styles.screen}>
      <View style={[styles.top, { paddingTop: insets.top + 8 }]}>
        <Logo height={40} />
        <Pressable accessibilityRole="button" accessibilityLabel="Search news" onPress={() => router.push({ pathname: '/news', params: { focus: '1' } })} style={styles.searchButton}>
          <Ionicons name="search" size={20} color={COLORS.black} />
        </Pressable>
      </View>

      <StateView loading={loading} error={!home ? error : null} onRetry={retry}>
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        >
          <LiveCard />

          {home?.hero?.length ? (
            <View style={styles.block}>
              <HeroCarousel articles={home.hero} />
            </View>
          ) : null}

          {home?.categories?.length ? (
            <FlatList
              horizontal
              data={home.categories}
              keyExtractor={(c) => String(c.id)}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.chips}
              style={styles.block}
              renderItem={({ item }) => (
                <Chip label={item.name} icon={categoryIcon(item.icon)} color={item.color} onPress={() => router.push({ pathname: '/news', params: { category: item.slug } })} />
              )}
            />
          ) : null}

          {home?.latest?.length ? (
            <View style={styles.block}>
              <SectionHeader title="Latest news" onAction={() => router.push('/news')} />
              <View style={styles.listCard}>
                {home.latest.slice(0, 6).map((article, i) => (
                  <View key={article.id} style={[styles.listItem, i > 0 && styles.divider]}>
                    <ArticleCard article={article} variant="row" />
                  </View>
                ))}
              </View>
            </View>
          ) : null}

          {home?.shows?.length ? (
            <View style={styles.block}>
              <SectionHeader title="Previous shows" onAction={() => router.push('/shows')} />
              <FlatList
                horizontal
                data={home.shows}
                keyExtractor={(s) => String(s.id)}
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.shows}
                renderItem={({ item }) => <ShowCard show={item} variant="tile" />}
              />
            </View>
          ) : null}

          {home?.trending?.length ? (
            <View style={styles.block}>
              <SectionHeader title="Trending now" actionLabel="" />
              <View style={styles.listCard}>
                {home.trending.map((article, i) => (
                  <View key={article.id} style={[styles.trendItem, i > 0 && styles.divider]}>
                    <AppText variant="title" style={[styles.rank, { color: article.category?.color || COLORS.primary }]}>
                      {i + 1}
                    </AppText>
                    <View style={{ flex: 1 }}>
                      <ArticleCard article={article} variant="row" />
                    </View>
                  </View>
                ))}
              </View>
            </View>
          ) : null}
        </ScrollView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md, paddingBottom: 10, backgroundColor: COLORS.white, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  searchButton: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.gray100, alignItems: 'center', justifyContent: 'center' },
  content: { paddingTop: SPACING.md, paddingBottom: SPACING.xl },
  block: { marginTop: SPACING.lg },
  chips: { paddingHorizontal: SPACING.md, gap: 8 },
  shows: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 6 },
  listCard: { marginHorizontal: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: SPACING.md, borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  listItem: { paddingVertical: 12 },
  trendItem: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  rank: { width: 26, textAlign: 'center' },
  divider: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
});
