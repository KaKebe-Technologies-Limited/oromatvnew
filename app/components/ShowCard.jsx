// A previous-show card: YouTube thumbnail with a play button, guest, title and date. "tile" has a fixed width for
// horizontal lists; "grid" fills its column in the Shows screen. Tapping opens the in-app show player.
import { Pressable, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { formatDate } from '../lib/format';
import AppText from './AppText';

export default function ShowCard({ show, variant = 'tile', style }) {
  return (
    <Pressable
      accessibilityRole="link"
      accessibilityLabel={show.title}
      onPress={() => router.push(`/show/${show.id}`)}
      style={({ pressed }) => [styles.card, variant === 'tile' ? styles.tile : styles.grid, pressed && { opacity: 0.9 }, style]}
    >
      <View style={styles.thumbWrap}>
        <Image source={{ uri: show.thumbnail }} contentFit="cover" transition={200} style={styles.thumb} />
        <View style={styles.playWrap}>
          <View style={styles.play}>
            <Ionicons name="play" size={20} color={COLORS.white} style={{ marginLeft: 2 }} />
          </View>
        </View>
        {show.kind === 'movie' ? (
          <View style={[styles.featured, { backgroundColor: '#7A1FA2' }]}>
            <Ionicons name="film" size={10} color={COLORS.white} />
            <AppText style={[styles.featuredText, { color: COLORS.white }]}>Movie</AppText>
          </View>
        ) : show.featured ? (
          <View style={styles.featured}>
            <Ionicons name="star" size={10} color={COLORS.ink} />
            <AppText style={styles.featuredText}>Featured</AppText>
          </View>
        ) : null}
      </View>
      <View style={styles.body}>
        {show.guest ? (
          <AppText variant="overline" numberOfLines={1}>
            {show.guest}
          </AppText>
        ) : null}
        <AppText variant="label" numberOfLines={2} style={styles.title}>
          {show.title}
        </AppText>
        {show.date ? (
          <AppText variant="caption" numberOfLines={1}>
            {formatDate(show.date)}
          </AppText>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, overflow: 'hidden', borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  tile: { width: 240 },
  grid: { flex: 1 },
  thumbWrap: { aspectRatio: 16 / 9, backgroundColor: COLORS.ink },
  thumb: { ...StyleSheet.absoluteFillObject },
  playWrap: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center' },
  play: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(128,0,0,0.88)', alignItems: 'center', justifyContent: 'center' },
  featured: { position: 'absolute', top: 8, left: 8, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.gold, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 5 },
  featuredText: { color: COLORS.ink, fontSize: 10, lineHeight: 13, fontWeight: '800', letterSpacing: 0.4, textTransform: 'uppercase' },
  body: { padding: SPACING.sm + 4, gap: 3 },
  title: { fontSize: 13.5, lineHeight: 18 },
});
