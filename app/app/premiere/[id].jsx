// Premiere screen: a programme's own page (opened from the Live tab's guide, the Shows tab, or a shared link). Counts down to
// the start using the server's clock; the moment it goes on air the app takes the viewer to the Live tab, where it starts by
// itself at the right second. Also: share, and "Add to calendar" (opens Google Calendar with the details filled in).
import { useEffect, useRef, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { formatWhen } from '../../lib/format';
import { openLink, shareLink } from '../../lib/links';
import { useFetch } from '../../lib/useFetch';

const pad = (n) => String(n).padStart(2, '0');
/** 2026-10-15T20:00:00+03:00 -> "20261015T170000Z" (what calendars expect). */
const calendarStamp = (iso) => new Date(iso).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');

function CountBox({ value, label }) {
  return (
    <View style={styles.box}>
      <AppText style={styles.boxValue}>{pad(value)}</AppText>
      <AppText style={styles.boxLabel}>{label}</AppText>
    </View>
  );
}

export default function PremiereScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, refreshQuietly } = useFetch(`/programmes/${id}`);
  const p = data?.data;
  const skew = p?.server_time ? Date.parse(p.server_time) - Date.now() : 0;
  const [now, setNow] = useState(Date.now());
  const sent = useRef(false);

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);

  // Ask the server again now and then (and often once the time is close), so "it is on" is never missed.
  const left = p ? Date.parse(p.starts_at) - (now + skew) : Infinity;
  const close = left < 90000;
  useEffect(() => {
    const timer = setInterval(refreshQuietly, close ? 3000 : 20000);
    return () => clearInterval(timer);
  }, [refreshQuietly, close]);

  // On air: go to the Live tab, where it plays.
  useEffect(() => {
    if (p?.on_air && !sent.current) {
      sent.current = true;
      router.replace('/live');
    }
  }, [p?.on_air]);

  const seconds = Math.max(0, Math.floor(left / 1000));
  const calendarUrl = p
    ? `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(`${p.title} — Oroma TV`)}&dates=${calendarStamp(p.starts_at)}/${calendarStamp(p.ends_at || new Date(Date.parse(p.starts_at) + 3600000).toISOString())}&details=${encodeURIComponent(`Watch free on Oroma TV: ${p.share_url}`)}`
    : '';

  return (
    <View style={styles.screen}>
      <ScreenHeader title={p ? p.kind_label : 'Premiere'} dark />
      <StateView loading={loading} error={error} onRetry={retry}>
        {p ? (
          <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: SPACING.xxl }}>
            <View style={styles.poster}>
              {p.thumbnail ? <Image source={{ uri: p.thumbnail }} contentFit="cover" style={StyleSheet.absoluteFill} /> : <Ionicons name="film" size={64} color={COLORS.gold} />}
              <View style={styles.posterShade} />
              <View style={styles.kind}>
                <Ionicons name={p.kind === 'movie' ? 'film' : 'radio'} size={13} color={COLORS.ink} />
                <AppText style={styles.kindText}>{p.kind_label}</AppText>
              </View>
            </View>

            <View style={styles.body}>
              <AppText variant="title" style={styles.title}>
                {p.title}
              </AppText>
              {p.description ? <AppText style={styles.desc}>{p.description}</AppText> : null}
              <View style={styles.whenRow}>
                <Ionicons name="calendar" size={16} color={COLORS.goldLight} />
                <AppText style={styles.when}>{formatWhen(p.starts_at, skew)}</AppText>
              </View>

              {p.state === 'upcoming' ? (
                <>
                  <View style={styles.count}>
                    <CountBox value={Math.floor(seconds / 86400)} label="days" />
                    <CountBox value={Math.floor((seconds % 86400) / 3600)} label="hours" />
                    <CountBox value={Math.floor((seconds % 3600) / 60)} label="min" />
                    <CountBox value={seconds % 60} label="sec" />
                  </View>
                  <AppText style={styles.hint}>Free to watch. When the countdown ends this opens the Live tab by itself — and everyone watches together.</AppText>
                </>
              ) : p.state === 'live' ? (
                <Button title="It’s on — watch now" icon="play" onPress={() => router.replace('/live')} style={{ marginTop: SPACING.md }} />
              ) : (
                <View style={styles.ended}>
                  <Ionicons name="checkmark-circle" size={22} color={COLORS.gold} />
                  <View style={{ flex: 1 }}>
                    <AppText variant="label" style={{ color: COLORS.white }}>
                      This programme has finished
                    </AppText>
                    <AppText variant="caption" style={{ color: 'rgba(255,255,255,0.7)', marginTop: 2 }}>
                      If it was saved you will find it in the Shows tab.
                    </AppText>
                  </View>
                </View>
              )}

              <View style={styles.actions}>
                {p.state === 'upcoming' ? <Button title="Add to calendar" icon="calendar-outline" small variant="light" onPress={() => openLink(calendarUrl)} /> : null}
                <Button title="Share" icon="share-social" variant="ghost" small onPress={() => shareLink({ title: p.title, url: p.share_url })} />
                <Button title={p.state === 'ended' ? 'Browse shows' : 'Watch live'} icon={p.state === 'ended' ? 'film-outline' : 'tv'} variant="ghost" small onPress={() => router.replace(p.state === 'ended' ? '/shows' : '/live')} />
              </View>
            </View>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.ink },
  poster: { width: '100%', aspectRatio: 16 / 9, backgroundColor: '#140808', alignItems: 'center', justifyContent: 'center' },
  posterShade: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(11,18,32,0.25)' },
  kind: { position: 'absolute', left: SPACING.md, bottom: SPACING.md, flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: COLORS.gold, paddingHorizontal: 11, paddingVertical: 5, borderRadius: RADIUS.full },
  kindText: { color: COLORS.ink, fontSize: 11.5, lineHeight: 15, fontWeight: '800', letterSpacing: 0.5, textTransform: 'uppercase' },
  body: { padding: SPACING.md, gap: 8 },
  title: { color: COLORS.white, fontSize: 24, lineHeight: 31 },
  desc: { color: 'rgba(255,255,255,0.8)', lineHeight: 22 },
  whenRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 2 },
  when: { color: COLORS.goldLight, fontFamily: FONTS.semibold },
  count: { flexDirection: 'row', gap: 10, marginTop: SPACING.md },
  box: { flex: 1, alignItems: 'center', paddingVertical: 12, borderRadius: RADIUS.md, backgroundColor: 'rgba(255,255,255,0.1)', borderWidth: StyleSheet.hairlineWidth, borderColor: 'rgba(255,255,255,0.2)' },
  boxValue: { color: COLORS.white, fontFamily: FONTS.headingXBold, fontSize: 26, lineHeight: 32, fontVariant: ['tabular-nums'] },
  boxLabel: { color: 'rgba(255,255,255,0.65)', fontSize: 10.5, lineHeight: 14, fontWeight: '700', letterSpacing: 0.8, textTransform: 'uppercase' },
  hint: { color: 'rgba(255,255,255,0.65)', fontSize: 13, lineHeight: 19, marginTop: 6 },
  ended: { flexDirection: 'row', gap: 12, alignItems: 'center', padding: SPACING.md, borderRadius: RADIUS.lg, backgroundColor: 'rgba(255,255,255,0.08)', marginTop: SPACING.md },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: SPACING.md },
});
