// Shows tab: the library, made to browse. A big featured show on top, what is on air or premiering next, a "Premieres" rail, a
// "Movie night" rail, filter chips (All / Shows / Movies), then everything in a two-column grid. Tapping a show plays it in the
// app; tapping a premiere opens its countdown page.
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { useState } from 'react';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import SectionHeader from '../../components/SectionHeader';
import ShowCard from '../../components/ShowCard';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { countdown, formatWhen } from '../../lib/format';
import { useLiveStatus } from '../../lib/liveStatus';
import { useFetch } from '../../lib/useFetch';
import { usePaged } from '../../lib/usePaged';

const FILTERS = [
  { key: 'all', label: 'All', icon: 'apps' },
  { key: 'show', label: 'Shows', icon: 'mic' },
  { key: 'movie', label: 'Movies', icon: 'film' },
];

/** The big featured show at the top: the picture, a dark fade, its title and a Watch now button. */
function Hero({ show }) {
  return (
    <Pressable accessibilityRole="link" accessibilityLabel={`Watch ${show.title}`} onPress={() => router.push(`/show/${show.id}`)} style={({ pressed }) => [styles.hero, pressed && { opacity: 0.94 }]}>
      <Image source={{ uri: show.thumbnail }} contentFit="cover" transition={250} style={StyleSheet.absoluteFill} />
      <LinearGradient colors={['rgba(11,18,32,0)', 'rgba(11,18,32,0.55)', 'rgba(11,18,32,0.95)']} style={StyleSheet.absoluteFill} />
      <View style={styles.heroBody}>
        <View style={styles.heroTag}>
          <Ionicons name={show.kind === 'movie' ? 'film' : 'star'} size={11} color={COLORS.ink} />
          <AppText style={styles.heroTagText}>{show.kind === 'movie' ? 'Featured movie' : 'Featured episode'}</AppText>
        </View>
        <AppText variant="title" numberOfLines={3} style={styles.heroTitle}>
          {show.title}
        </AppText>
        <View style={styles.heroPlay}>
          <Ionicons name="play" size={16} color={COLORS.primary} style={{ marginLeft: 1 }} />
          <AppText style={styles.heroPlayText}>Watch now</AppText>
        </View>
      </View>
    </Pressable>
  );
}

/** "On air now — join" or "Next premiere — in 2h", right under the header. */
function LiveBanner() {
  const { status, title, next, skew } = useLiveStatus();
  if (status === 'live') {
    return (
      <Pressable accessibilityRole="link" onPress={() => router.push('/live')} style={[styles.banner, { backgroundColor: COLORS.primary }]}>
        <View style={styles.bannerDot} />
        <View style={{ flex: 1 }}>
          <AppText style={styles.bannerKicker}>ON AIR NOW</AppText>
          <AppText style={styles.bannerTitle} numberOfLines={1}>
            {title || 'Oroma TV Live'}
          </AppText>
        </View>
        <AppText style={styles.bannerGo}>Join</AppText>
        <Ionicons name="arrow-forward" size={16} color={COLORS.white} />
      </Pressable>
    );
  }
  if (next) {
    return (
      <Pressable accessibilityRole="link" onPress={() => router.push(`/premiere/${next.id}`)} style={[styles.banner, { backgroundColor: COLORS.ink }]}>
        <Ionicons name={next.kind === 'movie' ? 'film' : 'time'} size={20} color={COLORS.gold} />
        <View style={{ flex: 1 }}>
          <AppText style={[styles.bannerKicker, { color: COLORS.goldLight }]}>NEXT UP · {String(next.kind_label ?? 'Programme').toUpperCase()}</AppText>
          <AppText style={styles.bannerTitle} numberOfLines={1}>
            {next.title}
          </AppText>
        </View>
        <AppText style={[styles.bannerGo, { color: COLORS.goldLight }]}>{countdown(next.starts_at, skew)}</AppText>
      </Pressable>
    );
  }
  return null;
}

/** A card in the Premieres rail. */
function PremiereTile({ item, skew }) {
  return (
    <Pressable accessibilityRole="link" accessibilityLabel={item.title} onPress={() => router.push(`/premiere/${item.id}`)} style={({ pressed }) => [styles.tile, pressed && { opacity: 0.9 }]}>
      <View style={styles.tileThumb}>
        {item.thumbnail ? <Image source={{ uri: item.thumbnail }} contentFit="cover" style={StyleSheet.absoluteFill} /> : null}
        <View style={[styles.tileTag, item.kind === 'movie' && { backgroundColor: '#7A1FA2' }]}>
          <Ionicons name={item.kind === 'movie' ? 'film' : 'flash'} size={10} color={COLORS.white} />
          <AppText style={styles.tileTagText}>{item.kind_label}</AppText>
        </View>
      </View>
      <View style={{ padding: SPACING.sm + 4, gap: 3 }}>
        <AppText variant="overline" numberOfLines={1}>
          {formatWhen(item.starts_at, skew)}
        </AppText>
        <AppText variant="label" numberOfLines={2} style={{ lineHeight: 18 }}>
          {item.title}
        </AppText>
        <AppText variant="caption">{countdown(item.starts_at, skew)}</AppText>
      </View>
    </Pressable>
  );
}

export default function ShowsScreen() {
  const insets = useSafeAreaInsets();
  const [filter, setFilter] = useState('all');
  const list = usePaged('/shows', filter === 'all' ? { per_page: 20 } : { per_page: 20, kind: filter });
  const schedule = useFetch('/schedule', { params: { hours: 24 * 30, limit: 10 } });
  const movies = useFetch('/shows', { params: { kind: 'movie', per_page: 10 } });

  const premieres = schedule.data?.data?.upcoming ?? [];
  const skew = schedule.data?.data?.server_time ? Date.parse(schedule.data.data.server_time) - Date.now() : 0;
  const movieRail = movies.data?.data ?? [];
  const movieCount = movies.data?.meta?.total ?? 0;
  const browsing = filter === 'all'; // the rails are for browsing; a filter shows one clear list
  const hero = browsing ? list.items.find((s) => s.featured) ?? list.items[0] : null;
  const gridItems = hero ? list.items.filter((s) => s.id !== hero.id) : list.items;

  // An odd number of shows leaves a gap in the last row; a blank cell keeps the final card from stretching full width.
  const data = gridItems.length % 2 ? [...gridItems, { id: 'pad', pad: true }] : gridItems;

  const reloadAll = () => {
    list.reload();
    schedule.reload();
    movies.reload();
  };

  const header = (
    <View>
      <LiveBanner />
      {hero ? <Hero show={hero} /> : null}

      {browsing && premieres.length ? (
        <View style={styles.section}>
          <SectionHeader title="Premieres & coming soon" actionLabel="Live" onAction={() => router.push('/live')} />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
            {premieres.map((item) => (
              <PremiereTile key={item.id} item={item} skew={skew} />
            ))}
          </ScrollView>
        </View>
      ) : null}

      {browsing && movieRail.length ? (
        <View style={styles.section}>
          <SectionHeader title="Movie night" />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
            {movieRail.map((item) => (
              <ShowCard key={item.id} show={item} variant="tile" />
            ))}
          </ScrollView>
        </View>
      ) : null}

      <View style={styles.chips}>
        {FILTERS.filter((f) => f.key !== 'movie' || movieCount > 0 || filter === 'movie').map((f) => (
          <Chip key={f.key} label={f.label} icon={f.icon} selected={filter === f.key} onPress={() => setFilter(f.key)} />
        ))}
      </View>
      <AppText variant="caption" style={styles.count}>
        {list.total ? `${list.total} title${list.total === 1 ? '' : 's'} on demand` : ' '}
      </AppText>
    </View>
  );

  return (
    <View style={styles.screen}>
      <View style={[styles.header, { paddingTop: insets.top + 10 }]}>
        <AppText variant="title">Shows & movies</AppText>
        <AppText variant="caption" style={{ marginTop: 2 }}>
          Past broadcasts, interviews, premieres and movies — free, any time
        </AppText>
      </View>

      {list.loading && list.items.length === 0 ? (
        <StateView loading />
      ) : list.error && list.items.length === 0 ? (
        <StateView error={list.error} onRetry={list.retry} />
      ) : (
        <FlatList
          data={data}
          numColumns={2}
          keyExtractor={(s) => String(s.id)}
          columnWrapperStyle={{ gap: 12 }}
          contentContainerStyle={styles.list}
          showsVerticalScrollIndicator={false}
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.6}
          ListHeaderComponent={header}
          refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={reloadAll} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          ListEmptyComponent={<StateView empty emptyIcon="film-outline" emptyTitle={filter === 'movie' ? 'No movies yet' : 'No shows yet'} emptyMessage="New titles will appear here." />}
          ListFooterComponent={list.loadingMore ? <ActivityIndicator color={COLORS.primary} style={{ marginVertical: SPACING.lg }} /> : null}
          renderItem={({ item }) => (item.pad ? <View style={{ flex: 1 }} /> : <ShowCard show={item} variant="grid" />)}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  header: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.sm + 4, backgroundColor: COLORS.white, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  list: { padding: SPACING.md, gap: 12, flexGrow: 1 },
  banner: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: RADIUS.lg, marginBottom: SPACING.md },
  bannerDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: COLORS.white },
  bannerKicker: { color: 'rgba(255,255,255,0.8)', fontSize: 10.5, lineHeight: 14, fontWeight: '800', letterSpacing: 0.8 },
  bannerTitle: { color: COLORS.white, fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20 },
  bannerGo: { color: COLORS.white, fontFamily: FONTS.bold, fontSize: 13 },
  hero: { width: '100%', aspectRatio: 16 / 10, borderRadius: RADIUS.xl, overflow: 'hidden', backgroundColor: COLORS.ink, marginBottom: SPACING.md, ...SHADOW },
  heroBody: { position: 'absolute', left: 0, right: 0, bottom: 0, padding: SPACING.md, gap: 8 },
  heroTag: { flexDirection: 'row', alignItems: 'center', gap: 5, alignSelf: 'flex-start', backgroundColor: COLORS.gold, paddingHorizontal: 9, paddingVertical: 3, borderRadius: RADIUS.full },
  heroTagText: { color: COLORS.ink, fontSize: 10.5, lineHeight: 14, fontWeight: '800', letterSpacing: 0.4, textTransform: 'uppercase' },
  heroTitle: { color: COLORS.white, fontSize: 19, lineHeight: 25 },
  heroPlay: { flexDirection: 'row', alignItems: 'center', gap: 7, alignSelf: 'flex-start', backgroundColor: COLORS.white, paddingHorizontal: 16, height: 38, borderRadius: RADIUS.full },
  heroPlayText: { color: COLORS.primary, fontFamily: FONTS.bold, fontSize: 13.5 },
  section: { marginBottom: SPACING.md, marginHorizontal: -SPACING.md },
  rail: { paddingHorizontal: SPACING.md, gap: 12 },
  tile: { width: 220, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, overflow: 'hidden', borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  tileThumb: { aspectRatio: 16 / 9, backgroundColor: COLORS.ink },
  tileTag: { position: 'absolute', top: 8, left: 8, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.live, paddingHorizontal: 8, paddingVertical: 3, borderRadius: RADIUS.full },
  tileTagText: { color: COLORS.white, fontSize: 10, lineHeight: 13, fontWeight: '800', letterSpacing: 0.4, textTransform: 'uppercase' },
  chips: { flexDirection: 'row', gap: 8, marginTop: SPACING.sm },
  count: { marginTop: SPACING.sm + 2, marginBottom: SPACING.xs },
});
