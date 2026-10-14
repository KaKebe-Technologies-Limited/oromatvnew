// Live tab — the heart of the app. What plays here is decided in Admin → Streams & Live:
//   1. a scheduled programme (or the main channel) that is on air plays at the top and takes over by itself at its start time.
//      A recorded programme or movie is played from the server's clock, so everyone is at the same second, whenever they open
//      the app (components/YouTubePlayer.jsx does the syncing);
//   2. when nothing is on air, the off-air playlist plays, so the screen is never empty;
//   3. below: how many people are watching, Share and "Listen to Q FM", a "Next live" countdown, the programme guide (tap one for
//      its premiere page), and the radio / video channels viewers can pick instead.
// While this tab is open the app checks in every 30 seconds so the admin can see how many people are watching.
// Leaving the tab stops playback and lets the phone sleep again.
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { router, useFocusEffect } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { activateKeepAwakeAsync, deactivateKeepAwake } from 'expo-keep-awake';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import EmbedPlayer from '../../components/EmbedPlayer';
import LiveBadge from '../../components/LiveBadge';
import PlaylistPlayer from '../../components/PlaylistPlayer';
import QfmButton from '../../components/QfmButton';
import StateView from '../../components/StateView';
import YouTubePlayer from '../../components/YouTubePlayer';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { compactNumber, countdown, formatWhen } from '../../lib/format';
import { shareLink } from '../../lib/links';
import { usePresence } from '../../lib/presence';
import { useRadio } from '../../lib/radio';
import { useLiveFeed } from '../../lib/useLiveFeed';

const KEEP_AWAKE_TAG = 'oroma-live';

/** What kind of thing is on air, in words. */
function kindLabel(stage) {
  if (stage.kind === 'main') return 'Live broadcast';
  return { movie: 'Movie premiere', event: 'Special event' }[stage.programme_kind] ?? 'Programme';
}

function ChannelRow({ channel, active, onPress }) {
  const radio = channel.type === 'radio';
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={`Play ${channel.name}`} onPress={onPress} style={({ pressed }) => [styles.channel, active && styles.channelActive, pressed && { opacity: 0.9 }]}>
      {channel.thumbnail ? (
        <Image source={{ uri: channel.thumbnail }} contentFit="cover" style={styles.channelThumb} />
      ) : (
        <View style={[styles.channelThumb, styles.channelIcon]}>
          <Ionicons name={radio ? 'radio' : 'videocam'} size={22} color={COLORS.primary} />
        </View>
      )}
      <View style={{ flex: 1 }}>
        <AppText variant="label" numberOfLines={2}>
          {channel.name}
        </AppText>
        <AppText variant="caption">{radio ? 'Radio' : 'Video'}</AppText>
      </View>
      <Ionicons name={active ? 'volume-high' : 'play-circle'} size={26} color={COLORS.primary} />
    </Pressable>
  );
}

/** "Next live: <title> — Today, 6:00 PM · in 2h 15m", refreshed every few seconds. Tap for its premiere page. */
function NextLiveCard({ next, skew, onAir }) {
  const [, setTick] = useState(0);
  useEffect(() => {
    const timer = setInterval(() => setTick((n) => n + 1), 15000);
    return () => clearInterval(timer);
  }, []);

  return (
    <Pressable accessibilityRole="link" accessibilityLabel={`${next.title}, open the premiere page`} onPress={() => router.push(`/premiere/${next.id}`)} style={({ pressed }) => [styles.next, pressed && { opacity: 0.9 }]}>
      <View style={styles.nextIcon}>
        <Ionicons name={next.kind === 'movie' ? 'film' : 'calendar'} size={22} color={COLORS.ink} />
      </View>
      <View style={{ flex: 1 }}>
        <AppText variant="overline" style={{ color: COLORS.goldLight }}>
          {onAir ? 'Coming up' : 'Next live'} · {next.kind_label ?? 'Programme'}
        </AppText>
        <AppText variant="subheading" numberOfLines={2} style={{ color: COLORS.white }}>
          {next.title}
        </AppText>
        <AppText variant="caption" style={{ color: 'rgba(255,255,255,0.78)', marginTop: 2 }}>
          {formatWhen(next.starts_at, skew)} · {countdown(next.starts_at, skew)}
        </AppText>
      </View>
      <Ionicons name="chevron-forward" size={20} color="rgba(255,255,255,0.7)" />
    </Pressable>
  );
}

/** One row of the programme guide. */
function GuideRow({ item, skew }) {
  return (
    <Pressable accessibilityRole="link" accessibilityLabel={item.title} onPress={() => router.push(`/premiere/${item.id}`)} style={({ pressed }) => [styles.guideRow, pressed && { opacity: 0.88 }]}>
      <View style={styles.guideTime}>
        <AppText style={styles.guideTimeText}>{formatWhen(item.starts_at, skew)}</AppText>
      </View>
      <View style={{ flex: 1 }}>
        <AppText variant="label" numberOfLines={2} style={{ color: COLORS.white }}>
          {item.title}
        </AppText>
        <AppText variant="caption" style={{ color: item.kind === 'movie' ? COLORS.goldLight : 'rgba(255,255,255,0.6)' }}>
          {item.kind_label ?? 'Programme'}
        </AppText>
      </View>
      <Ionicons name="chevron-forward" size={18} color="rgba(255,255,255,0.45)" />
    </Pressable>
  );
}

export default function LiveScreen() {
  const insets = useSafeAreaInsets();
  const [focused, setFocused] = useState(false);
  const { live, loading, refreshing, error, retry, reload, refreshQuietly, skew } = useLiveFeed(focused);
  const [selected, setSelected] = useState(null); // a channel the viewer picked, or null for the main stage
  const counted = usePresence(focused && !!live);
  const radio = useRadio();
  const radioOn = radio.phase === 'playing' || radio.phase === 'loading';

  // Only play (and keep the screen awake) while this tab is on screen.
  useFocusEffect(
    useCallback(() => {
      setFocused(true);
      activateKeepAwakeAsync(KEEP_AWAKE_TAG).catch(() => {});
      return () => {
        setFocused(false);
        deactivateKeepAwake(KEEP_AWAKE_TAG).catch(() => {});
      };
    }, []),
  );

  // When a programme goes on air, viewers who had picked a channel are switched over to it.
  const stage = live?.stage ?? null;
  const stageId = stage?.id ?? null;
  const seenStage = useRef(null);
  useEffect(() => {
    if (stageId && seenStage.current !== stageId) setSelected(null);
    seenStage.current = stageId;
  }, [stageId]);

  // A recorded programme is played from the server's clock: when it began, as this phone's clock shows it. Worked out once per
  // programme, so a small change in the clock difference later does not restart the video.
  const syncStart = useMemo(() => (stage?.mode === 'premiere' && stage.starts_at ? Date.parse(stage.starts_at) - skew : 0), [stageId]); // eslint-disable-line react-hooks/exhaustive-deps

  const playlist = live?.playlist ?? [];
  const guide = (live?.schedule ?? []).slice(1, 8);
  const radios = (live?.channels ?? []).filter((c) => c.type === 'radio');
  const videos = (live?.channels ?? []).filter((c) => c.type !== 'radio');
  const playingRadio = selected?.type === 'radio';
  const playingPlaylist = !selected && !stage && playlist.length > 0;
  const nothingPlaying = !selected && !stage && playlist.length === 0;
  const watching = counted ?? live?.watching ?? 0;
  const qfmName = live?.radio?.name ?? 'Q FM';

  // The player for whatever is showing right now.
  let player;
  if (!focused) {
    player = <View style={[styles.stage, styles.stageVideo, styles.stageIdle]}><Ionicons name="play-circle" size={54} color="rgba(255,255,255,0.5)" /></View>;
  } else if (selected) {
    player =
      selected.type === 'radio' ? (
        <EmbedPlayer key={`radio-${selected.id}`} src={selected.url} referrer={live.referrer} height={240} fullscreenable={false} />
      ) : (
        <YouTubePlayer key={`channel-${selected.id}`} videoId={selected.video_id} watchUrl={selected.watch_url} referrer={live.referrer} muted={radioOn} />
      );
  } else if (stage) {
    player =
      stage.provider === 'youtube' && stage.video_id ? (
        <YouTubePlayer
          key={stage.id}
          videoId={stage.video_id}
          watchUrl={stage.watch_url}
          referrer={live.referrer}
          syncStart={syncStart}
          muted={radioOn}
          endedText={live.next ? `That was the end of the programme. Up next: ${live.next.title}` : 'That was the end of the programme. Browse our previous shows in the Shows tab.'}
          onEnded={refreshQuietly}
        />
      ) : (
        <EmbedPlayer key={stage.id} src={stage.embed_url} referrer={live.referrer} />
      );
  } else if (playlist.length) {
    player = <PlaylistPlayer items={playlist} shuffle={live.shuffle} referrer={live.referrer} muted={radioOn} />;
  } else {
    player = (
      <View style={[styles.stage, styles.stageVideo, styles.stageIdle]}>
        <Ionicons name="moon" size={40} color="rgba(255,255,255,0.55)" />
        <AppText style={{ color: 'rgba(255,255,255,0.7)', marginTop: 8 }}>We are off air right now</AppText>
      </View>
    );
  }

  const watchingChip =
    watching >= 2 ? (
      <View style={styles.watching} accessibilityLabel={`${watching} people watching`}>
        <Ionicons name="eye" size={13} color={COLORS.white} />
        <AppText style={styles.watchingText}>{compactNumber(watching)} watching</AppText>
      </View>
    ) : null;

  return (
    <View style={styles.screen}>
      <View style={[styles.top, { paddingTop: insets.top + 8 }]}>
        <AppText variant="title" style={{ color: COLORS.white }}>
          Live
        </AppText>
        <View style={styles.topRight}>
          {watchingChip}
          {live ? <LiveBadge status={live.status} /> : null}
        </View>
      </View>

      <StateView loading={loading} error={!live ? error : null} onRetry={retry}>
        <ScrollView showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.white} colors={[COLORS.primary]} progressBackgroundColor={COLORS.white} />} contentContainerStyle={styles.content}>
          {player}

          {!playingPlaylist ? (
            <View style={styles.info}>
              {!selected && stage ? (
                <AppText variant="overline" style={{ color: COLORS.goldLight }}>
                  {kindLabel(stage)}
                  {stage.mode === 'premiere' && stage.kind === 'program' ? ' · everyone watches together' : ''}
                </AppText>
              ) : null}
              <AppText variant="title" style={styles.infoTitle} numberOfLines={3}>
                {selected ? selected.name : stage ? stage.title : live?.title}
              </AppText>
              <AppText style={styles.infoSub}>
                {selected
                  ? playingRadio
                    ? 'Radio'
                    : 'Video channel'
                  : stage
                    ? stage.description || 'You are watching the live broadcast.'
                    : live?.tagline
                      ? `“${live.tagline}”`
                      : ''}
              </AppText>
              <View style={styles.actions}>
                {selected ? <Button title="Back to Oroma TV Live" icon="tv" small onPress={() => setSelected(null)} /> : null}
                <Button title="Share" icon="share-social" variant="ghost" small onPress={() => shareLink({ title: selected ? selected.name : stage ? stage.title : 'Watch Oroma TV live', url: selected?.watch_url || stage?.share_url || live.share_url })} />
                {live?.radio?.enabled ? <QfmButton name={qfmName} /> : null}
              </View>
            </View>
          ) : (
            <View style={[styles.actions, { paddingHorizontal: SPACING.md, marginTop: SPACING.md }]}>
              <Button title="Share" icon="share-social" variant="ghost" small onPress={() => shareLink({ title: 'Watch Oroma TV live', url: live.share_url })} />
              {live?.radio?.enabled ? <QfmButton name={qfmName} /> : null}
            </View>
          )}

          {live?.next ? <NextLiveCard next={live.next} skew={skew} onAir={!!stage} /> : null}

          {guide.length ? (
            <View style={styles.group}>
              <AppText variant="heading" style={styles.groupTitle}>
                Later on
              </AppText>
              {guide.map((item) => (
                <GuideRow key={item.id} item={item} skew={skew} />
              ))}
            </View>
          ) : null}

          {nothingPlaying ? (
            <View style={styles.notice}>
              <Ionicons name="moon" size={22} color={COLORS.primary} />
              <View style={{ flex: 1 }}>
                <AppText variant="label">We are not broadcasting right now</AppText>
                <AppText variant="caption" style={{ marginTop: 2 }}>
                  {live?.next ? 'This screen goes live by itself when the next programme starts.' : 'The player will come alive as soon as Oroma TV goes on air.'} Meanwhile, watch our previous shows.
                </AppText>
                <Button title="Browse previous shows" small variant="outline" onPress={() => router.push('/shows')} style={{ marginTop: SPACING.sm + 4, alignSelf: 'flex-start' }} />
              </View>
            </View>
          ) : null}

          {radios.length ? (
            <View style={styles.group}>
              <AppText variant="heading" style={styles.groupTitle}>
                Radio
              </AppText>
              {radios.map((c) => (
                <ChannelRow key={c.id} channel={c} active={selected?.id === c.id} onPress={() => setSelected(c)} />
              ))}
            </View>
          ) : null}

          {videos.length ? (
            <View style={styles.group}>
              <AppText variant="heading" style={styles.groupTitle}>
                More videos
              </AppText>
              {videos.map((c) => (
                <ChannelRow key={c.id} channel={c} active={selected?.id === c.id} onPress={() => setSelected(c)} />
              ))}
            </View>
          ) : null}
        </ScrollView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.ink },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md, paddingBottom: 12 },
  topRight: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  watching: { flexDirection: 'row', alignItems: 'center', gap: 5, height: 24, paddingHorizontal: 10, borderRadius: RADIUS.full, backgroundColor: 'rgba(255,255,255,0.14)' },
  watchingText: { color: COLORS.white, fontSize: 11.5, lineHeight: 15, fontWeight: '700' },
  content: { paddingBottom: SPACING.xl },
  stage: { width: '100%', backgroundColor: '#000', overflow: 'hidden' },
  stageVideo: { aspectRatio: 16 / 9 },
  stageIdle: { alignItems: 'center', justifyContent: 'center' },
  info: { padding: SPACING.md, gap: 6 },
  infoTitle: { color: COLORS.white, fontSize: 20, lineHeight: 26 },
  infoSub: { color: 'rgba(255,255,255,0.72)' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 10, marginTop: SPACING.sm + 2 },
  next: { flexDirection: 'row', gap: 14, alignItems: 'center', marginHorizontal: SPACING.md, marginTop: SPACING.md, padding: SPACING.md, borderRadius: RADIUS.lg, backgroundColor: 'rgba(255,255,255,0.08)', borderLeftWidth: 4, borderLeftColor: COLORS.gold },
  nextIcon: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.gold, alignItems: 'center', justifyContent: 'center' },
  guideRow: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: RADIUS.md, backgroundColor: 'rgba(255,255,255,0.07)' },
  guideTime: { width: 92 },
  guideTimeText: { color: COLORS.goldLight, fontSize: 12, lineHeight: 16, fontWeight: '700' },
  notice: { flexDirection: 'row', gap: 12, alignItems: 'flex-start', marginHorizontal: SPACING.md, marginTop: SPACING.md, padding: SPACING.md, borderRadius: RADIUS.lg, backgroundColor: COLORS.white },
  group: { marginTop: SPACING.lg, paddingHorizontal: SPACING.md, gap: 10 },
  groupTitle: { color: COLORS.white, marginBottom: 2 },
  channel: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10, borderRadius: RADIUS.md, backgroundColor: COLORS.white, ...SHADOW },
  channelActive: { borderWidth: 2, borderColor: COLORS.gold },
  channelThumb: { width: 84, height: 54, borderRadius: RADIUS.sm, backgroundColor: COLORS.ink },
  channelIcon: { alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.primaryMist },
});
