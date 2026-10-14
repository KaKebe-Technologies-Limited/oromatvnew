// What plays while Oroma TV is off air: the videos the admin put in the off-air playlist (Admin → Streams & Live), one after
// another and round again when the last one ends — in our own player with Back / Stop / Forward / Fullscreen. Tapping a video
// in "Up next" jumps to it. As soon as a programme goes on air, the Live screen swaps this for the live stream.
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, RADIUS, SPACING } from '../constants/theme';
import { PUBLIC_SITE_URL } from '../lib/api';
import AppText from './AppText';
import YouTubePlayer from './YouTubePlayer';

/** A shuffled copy (Fisher–Yates). */
function shuffled(items) {
  const copy = [...items];
  for (let i = copy.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}

export default function PlaylistPlayer({ items, shuffle = false, muted = false, referrer = PUBLIC_SITE_URL }) {
  // The order is fixed for as long as the playlist stays the same, so a refresh does not reshuffle what is playing.
  const signature = `${shuffle ? 's' : 'o'}:${items.map((i) => i.id).join(',')}`;
  const list = useMemo(() => (shuffle ? shuffled(items) : items), [signature]); // eslint-disable-line react-hooks/exhaustive-deps
  const [index, setIndex] = useState(0);

  const position = list.length ? index % list.length : 0;
  const current = list[position];
  if (!current) return null;
  const upNext = list.map((item, i) => ({ item, i })).filter(({ i }) => i !== position);

  return (
    <View>
      {/* The key includes the position so a one-video playlist starts over when it ends. */}
      <YouTubePlayer key={`${current.video_id}-${position}-${index}`} videoId={current.video_id} referrer={referrer} muted={muted} onEnded={() => setIndex((n) => n + 1)} />

      <View style={styles.now}>
        <View style={styles.badge}>
          <Ionicons name="musical-notes" size={12} color={COLORS.ink} />
          <AppText style={styles.badgeText}>Playing while we are off air</AppText>
        </View>
        <AppText variant="title" style={styles.title} numberOfLines={3}>
          {current.title}
        </AppText>
      </View>

      {upNext.length ? (
        <View style={styles.list}>
          <AppText variant="heading" style={styles.heading}>
            Up next
          </AppText>
          {upNext.slice(0, 6).map(({ item, i }) => (
            <Pressable key={item.id} accessibilityRole="button" accessibilityLabel={`Play ${item.title}`} onPress={() => setIndex(i)} style={({ pressed }) => [styles.row, pressed && { opacity: 0.85 }]}>
              <Image source={{ uri: item.thumbnail }} contentFit="cover" style={styles.thumb} />
              <AppText variant="label" numberOfLines={2} style={{ flex: 1 }}>
                {item.title}
              </AppText>
              <Ionicons name="play-circle" size={26} color={COLORS.primary} />
            </Pressable>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  now: { paddingHorizontal: SPACING.md, paddingTop: SPACING.sm, gap: 8 },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', backgroundColor: COLORS.gold, paddingHorizontal: 10, paddingVertical: 4, borderRadius: RADIUS.full },
  badgeText: { color: COLORS.ink, fontSize: 11, lineHeight: 15, fontWeight: '800', letterSpacing: 0.4, textTransform: 'uppercase' },
  title: { color: COLORS.white, fontSize: 19, lineHeight: 25 },
  list: { marginTop: SPACING.lg, paddingHorizontal: SPACING.md, gap: 10 },
  heading: { color: COLORS.white, marginBottom: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10, borderRadius: RADIUS.md, backgroundColor: COLORS.white },
  thumb: { width: 84, height: 54, borderRadius: RADIUS.sm, backgroundColor: COLORS.ink },
});
