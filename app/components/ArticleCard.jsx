// Story cards: "hero" (full-bleed photo with the headline on it), "feature" (photo on top, text below) and "row"
// (small photo beside the text). All open the article screen when tapped.
import { Pressable, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, CATEGORY_FALLBACK, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { timeAgo } from '../lib/format';
import AppText from './AppText';

const open = (article) => router.push(`/article/${article.slug}`);
const accent = (article) => article.category?.color || CATEGORY_FALLBACK;

function Meta({ article, light = false, showRead = false }) {
  const color = light ? 'rgba(255,255,255,0.85)' : COLORS.gray700;
  return (
    <AppText variant="caption" numberOfLines={1} style={{ color }}>
      {article.author} · {timeAgo(article.published_at)}
      {showRead ? ` · ${article.reading_time} min read` : ''}
    </AppText>
  );
}

export default function ArticleCard({ article, variant = 'feature', style }) {
  if (variant === 'hero') {
    return (
      <Pressable accessibilityRole="link" accessibilityLabel={article.title} onPress={() => open(article)} style={[styles.hero, style]}>
        <Image source={{ uri: article.image }} contentFit="cover" transition={250} style={StyleSheet.absoluteFill} />
        <LinearGradient colors={['rgba(7,10,20,0)', 'rgba(7,10,20,0.55)', 'rgba(7,10,20,0.94)']} locations={[0.25, 0.6, 1]} style={StyleSheet.absoluteFill} />
        <View style={styles.heroBody}>
          {article.category ? (
            <View style={[styles.badge, { backgroundColor: accent(article) }]}>
              <AppText style={styles.badgeText}>{article.category.name}</AppText>
            </View>
          ) : null}
          <AppText variant="heading" numberOfLines={3} style={styles.heroTitle}>
            {article.title}
          </AppText>
          <Meta article={article} light />
        </View>
      </Pressable>
    );
  }

  if (variant === 'row') {
    return (
      <Pressable accessibilityRole="link" accessibilityLabel={article.title} onPress={() => open(article)} style={({ pressed }) => [styles.row, pressed && { opacity: 0.85 }, style]}>
        <Image source={{ uri: article.image }} contentFit="cover" transition={200} style={styles.rowImage} />
        <View style={styles.rowBody}>
          {article.category ? (
            <AppText variant="overline" numberOfLines={1} style={{ color: accent(article), fontSize: 10.5 }}>
              {article.category.name}
            </AppText>
          ) : null}
          <AppText variant="label" numberOfLines={3} style={styles.rowTitle}>
            {article.title}
          </AppText>
          <Meta article={article} />
        </View>
      </Pressable>
    );
  }

  return (
    <Pressable accessibilityRole="link" accessibilityLabel={article.title} onPress={() => open(article)} style={({ pressed }) => [styles.feature, pressed && { opacity: 0.92 }, style]}>
      <Image source={{ uri: article.image }} contentFit="cover" transition={250} style={styles.featureImage} />
      <View style={styles.featureBody}>
        {article.category ? (
          <AppText variant="overline" style={{ color: accent(article) }}>
            {article.category.name}
          </AppText>
        ) : null}
        <AppText variant="heading" numberOfLines={3} style={styles.featureTitle}>
          {article.title}
        </AppText>
        <Meta article={article} showRead />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  hero: { height: 250, borderRadius: RADIUS.lg, overflow: 'hidden', backgroundColor: COLORS.ink, justifyContent: 'flex-end', ...SHADOW },
  heroBody: { padding: SPACING.md, gap: 8 },
  heroTitle: { color: COLORS.white, fontSize: 19, lineHeight: 25 },
  badge: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 6 },
  badgeText: { color: COLORS.white, fontSize: 10.5, lineHeight: 14, fontWeight: '700', letterSpacing: 0.6, textTransform: 'uppercase' },

  feature: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, overflow: 'hidden', borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  featureImage: { width: '100%', aspectRatio: 16 / 9, backgroundColor: COLORS.ink },
  featureBody: { padding: SPACING.md, gap: 6 },
  featureTitle: { fontSize: 17, lineHeight: 23 },

  row: { flexDirection: 'row', gap: 12, alignItems: 'center' },
  rowImage: { width: 104, height: 80, borderRadius: RADIUS.md, backgroundColor: COLORS.ink },
  rowBody: { flex: 1, gap: 3 },
  rowTitle: { fontSize: 14, lineHeight: 19 },
});
