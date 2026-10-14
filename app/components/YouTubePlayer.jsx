// A YouTube video with none of YouTube's own controls: just the picture, and the app's own buttons underneath —
// Back 10 seconds, Stop / Play, Forward 10 seconds, and Fullscreen. The video is played by lib/youtubePage.js (YouTube's
// official player API with its controls switched off); this component keeps track of what the player is doing and draws
// the buttons. While the video is stopped, loading or has failed, the app covers the picture with its own screen.
//
// Fullscreen turns the phone sideways and shows the video edge to edge with the same buttons floating over it (they hide
// after a few seconds; tap the picture to bring them back). The video carries on from the same second.
//
// A synchronized programme (`syncStart` = when it began, on this phone's clock) is played from the server's clock, so everyone
// is at the same second, like TV. A small LIVE chip shows while the viewer is on the shared moment; if they press Back or Stop
// they fall behind on purpose and the chip becomes "Jump to live".
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { COLORS, RADIUS, SPACING } from '../constants/theme';
import { PUBLIC_SITE_URL } from '../lib/api';
import { openExternal } from '../lib/links';
import { lockLandscape, lockPortrait } from '../lib/orientation';
import { youtubePage } from '../lib/youtubePage';
import AppText from './AppText';
import Button from './Button';
import PlayerSurface from './PlayerSurface';

const LOAD_TIMEOUT_MS = 15000; // no answer from YouTube at all -> show the "could not load" screen
const AUTOPLAY_GRACE_MS = 5000; // the player is ready but nothing started (autoplay blocked) -> show the Play screen
const BAR_HIDE_MS = 3500; // fullscreen buttons fade away after this long
// YouTube flashes its own title, pause button and logo for a few seconds when a video starts, resumes or is skipped (its
// player cannot be told not to). The app covers the picture with its own screen while that happens.
const SHIELD_MS = 4000;
const BEHIND_SECONDS = 8; // further behind the shared moment than this -> offer "Jump to live"

function RoundButton({ icon, label, onPress, disabled, big, small }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [styles.buttonWrap, small && { minWidth: 0 }, disabled && { opacity: 0.4 }, pressed && { opacity: 0.8 }]}
    >
      <View style={[styles.button, big && styles.buttonBig, small && styles.buttonSmall, small && big && styles.buttonSmallBig]}>{icon}</View>
      {small ? null : <AppText style={styles.buttonLabel}>{label}</AppText>}
    </Pressable>
  );
}

export default function YouTubePlayer({ videoId, watchUrl, autoplay = true, referrer = PUBLIC_SITE_URL, syncStart = 0, muted = false, endedText, onEnded, style }) {
  const surface = useRef(null);
  const timer = useRef(null);
  const position = useRef(0); // the last known second, kept fresh by the page's ticks
  const resume = useRef({ autoplay, start: 0 }); // what the next (re)loaded player should do
  const [status, setStatus] = useState('loading'); // loading | playing | buffering | paused | ended | error
  const [attempt, setAttempt] = useState(0);
  const [full, setFull] = useState(false);
  const [barVisible, setBarVisible] = useState(true);
  const [shield, setShield] = useState(null); // null, or { skip: 'back' | 'forward' | null } while our own screen covers the picture
  const [lag, setLag] = useState(0); // synchronized programmes: seconds behind the shared moment
  const shieldTimer = useRef(null);
  const statusRef = useRef('loading');

  /** Cover the picture for a few seconds (optionally showing which way the viewer skipped). */
  const startShield = useCallback((skip) => {
    clearTimeout(shieldTimer.current);
    setShield((previous) => ({ skip: skip ?? previous?.skip ?? null }));
    shieldTimer.current = setTimeout(() => setShield(null), SHIELD_MS);
  }, []);
  useEffect(() => () => clearTimeout(shieldTimer.current), []);

  // A different video starts from the beginning (reset right here, before the page for it is built below).
  const shownVideo = useRef(videoId);
  if (shownVideo.current !== videoId) {
    shownVideo.current = videoId;
    position.current = 0;
    resume.current = { autoplay, start: 0 };
  }

  const html = useMemo(() => youtubePage(videoId, { ...resume.current, syncStart, muted }), [videoId, attempt, full]); // eslint-disable-line react-hooks/exhaustive-deps

  // A new video, a retry, or the switch to / from fullscreen loads a fresh player: start from "loading" and never leave
  // the viewer on an endless spinner.
  useEffect(() => {
    setStatus('loading');
    setShield(null);
    setLag(0);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setStatus((s) => (s === 'loading' ? 'error' : s)), LOAD_TIMEOUT_MS);
    return () => clearTimeout(timer.current);
  }, [videoId, attempt, full]);

  // Muting (the radio is on) keeps the picture running, so a synchronized programme never falls behind.
  useEffect(() => {
    surface.current?.run(muted ? 'mute' : 'unmute');
  }, [muted]);

  // If the screen is left while in fullscreen (a navigation, a reload), put the phone upright again.
  useEffect(() => () => void lockPortrait(), []);

  const onMessage = useCallback(
    (message) => {
      if (message.type === 'ready') {
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setStatus((s) => (s === 'loading' ? 'paused' : s)), AUTOPLAY_GRACE_MS);
      } else if (message.type === 'tick') {
        position.current = message.t || 0;
      } else if (message.type === 'sync') {
        setLag(message.lag || 0);
      } else if (message.type === 'state') {
        if (message.state === 'playing') {
          // Starting, resuming or arriving after a skip: YouTube's overlay is about to flash, so cover it.
          if (statusRef.current !== 'playing') startShield();
          setStatus('playing');
        } else if (message.state === 'paused') {
          setShield(null);
          setStatus('paused');
        } else if (message.state === 'ended') {
          setStatus('ended');
          onEnded?.();
        } else if (message.state === 'buffering') setStatus((s) => (s === 'loading' || s === 'playing' ? 'buffering' : s));
      } else if (message.type === 'error') {
        setStatus('error');
      }
    },
    [onEnded, startShield],
  );

  statusRef.current = status;
  const run = (command) => surface.current?.run(command);
  const skip = (direction) => {
    run(direction);
    startShield(direction);
  };
  const isPlaying = status === 'playing' || status === 'buffering';
  const behind = syncStart > 0 && lag > BEHIND_SECONDS;
  const jumpToLive = () => {
    run('live');
    setStatus('buffering');
    setLag(0);
    startShield();
  };
  const usable = status !== 'loading' && status !== 'error';

  const toggle = () => {
    run(isPlaying ? 'stop' : 'play');
    setStatus(isPlaying ? 'paused' : 'buffering');
    setBarVisible(true);
  };

  const enterFullscreen = async () => {
    resume.current = { autoplay: isPlaying, start: position.current, behind };
    await lockLandscape();
    setBarVisible(true);
    setFull(true);
  };
  const exitFullscreen = async () => {
    resume.current = { autoplay: isPlaying, start: position.current, behind };
    setFull(false);
    await lockPortrait();
  };

  // In fullscreen the buttons float over the picture and fade away while it plays.
  const barShown = barVisible || status !== 'playing';
  useEffect(() => {
    if (!full || !barVisible || status !== 'playing') return undefined;
    const hide = setTimeout(() => setBarVisible(false), BAR_HIDE_MS);
    return () => clearTimeout(hide);
  }, [full, barVisible, status]);

  const stage = (inFullscreen) => (
    <View style={inFullscreen ? styles.fullStage : styles.stage}>
      <PlayerSurface key={`${attempt}-${inFullscreen ? 'full' : 'inline'}`} ref={surface} html={html} baseUrl={referrer} onMessage={onMessage} onFailed={() => setStatus('error')} />

      {status === 'loading' ? (
        <View style={styles.cover}>
          <ActivityIndicator color={COLORS.white} size="large" />
        </View>
      ) : null}

      {status === 'ended' && syncStart > 0 ? (
        <View style={[styles.cover, { padding: SPACING.md }]}>
          <Ionicons name="checkmark-circle" size={44} color={COLORS.gold} />
          <AppText style={[styles.errorText, { marginTop: 8 }]}>{endedText || 'That was the end of the programme.'}</AppText>
        </View>
      ) : null}

      {status === 'paused' || (status === 'ended' && !syncStart) ? (
        <Pressable accessibilityRole="button" accessibilityLabel="Play" onPress={toggle} style={styles.cover}>
          <View style={styles.bigPlay}>
            <Ionicons name="play" size={34} color={COLORS.white} style={{ marginLeft: 4 }} />
          </View>
        </Pressable>
      ) : null}

      {status === 'error' ? (
        <View style={[styles.cover, { padding: SPACING.md, gap: SPACING.sm + 4 }]}>
          <AppText style={styles.errorText}>This video could not be played here.</AppText>
          <View style={styles.errorActions}>
            <Button title="Try again" small variant="light" onPress={() => setAttempt((n) => n + 1)} />
            {watchUrl ? <Button title="Open in YouTube" small variant="ghost" onPress={() => openExternal(watchUrl)} /> : null}
          </View>
        </View>
      ) : null}

      {/* The app's own screen over the picture while YouTube's overlay would be showing. */}
      {shield && (status === 'playing' || status === 'buffering') ? (
        <View style={styles.cover} pointerEvents="none">
          {shield.skip ? (
            <View style={styles.skipPill}>
              <MaterialCommunityIcons name={shield.skip === 'back' ? 'rewind-10' : 'fast-forward-10'} size={40} color={COLORS.white} />
            </View>
          ) : (
            <ActivityIndicator color={COLORS.white} size="large" />
          )}
        </View>
      ) : null}

      {syncStart > 0 && (status === 'playing' || status === 'buffering' || status === 'paused') ? (
        behind ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Jump to live" onPress={jumpToLive} style={[styles.chip, styles.chipBehind]}>
            <Ionicons name="play-forward" size={12} color={COLORS.ink} />
            <AppText style={[styles.chipText, { color: COLORS.ink }]}>Jump to live</AppText>
          </Pressable>
        ) : (
          <View style={styles.chip} pointerEvents="none">
            <View style={styles.chipDot} />
            <AppText style={styles.chipText}>LIVE</AppText>
          </View>
        )
      ) : null}

      {status === 'buffering' ? (
        <View style={styles.bufferingBadge} pointerEvents="none">
          <ActivityIndicator color={COLORS.white} size="small" />
        </View>
      ) : null}

      {inFullscreen ? (
        <>
          {/* A transparent layer over the picture: tapping it brings the buttons back. */}
          {status === 'playing' || status === 'buffering' ? <Pressable accessibilityLabel="Show controls" style={StyleSheet.absoluteFill} onPress={() => setBarVisible((v) => !v)} /> : null}

          {barShown ? (
            <>
              <View style={styles.fullTop} pointerEvents="box-none">
                <RoundButton small label="Exit fullscreen" onPress={exitFullscreen} icon={<MaterialCommunityIcons name="fullscreen-exit" size={26} color={COLORS.white} />} />
              </View>
              <View style={styles.fullBar} pointerEvents="box-none">
                <View style={styles.fullBarInner}>
                  <RoundButton small label="Back" disabled={!usable} onPress={() => skip('back')} icon={<MaterialCommunityIcons name="rewind-10" size={26} color={COLORS.white} />} />
                  <RoundButton small big label={isPlaying ? 'Stop' : 'Play'} disabled={!usable} onPress={toggle} icon={<MaterialCommunityIcons name={isPlaying ? 'stop' : 'play'} size={30} color={COLORS.white} />} />
                  <RoundButton small label="Forward" disabled={!usable} onPress={() => skip('forward')} icon={<MaterialCommunityIcons name="fast-forward-10" size={26} color={COLORS.white} />} />
                </View>
              </View>
            </>
          ) : null}
        </>
      ) : null}
    </View>
  );

  return (
    <View style={[styles.box, style]}>
      {/* While fullscreen, the picture lives in the modal below, so this spot only holds a black box. */}
      {full ? <View style={styles.stage} /> : stage(false)}

      <View style={styles.controls}>
        <RoundButton label="Back" disabled={!usable} onPress={() => skip('back')} icon={<MaterialCommunityIcons name="rewind-10" size={28} color={COLORS.white} />} />
        <RoundButton big label={isPlaying ? 'Stop' : 'Play'} disabled={!usable} onPress={toggle} icon={<MaterialCommunityIcons name={isPlaying ? 'stop' : 'play'} size={34} color={COLORS.white} />} />
        <RoundButton label="Forward" disabled={!usable} onPress={() => skip('forward')} icon={<MaterialCommunityIcons name="fast-forward-10" size={28} color={COLORS.white} />} />
        <RoundButton label="Fullscreen" disabled={!usable} onPress={enterFullscreen} icon={<MaterialCommunityIcons name="fullscreen" size={30} color={COLORS.white} />} />
      </View>

      <Modal visible={full} animationType="fade" statusBarTranslucent navigationBarTranslucent supportedOrientations={['landscape', 'landscape-left', 'landscape-right', 'portrait']} onRequestClose={exitFullscreen}>
        <StatusBar hidden />
        {stage(true)}
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  box: { backgroundColor: COLORS.ink },
  stage: { width: '100%', aspectRatio: 16 / 9, backgroundColor: '#000', overflow: 'hidden' },
  fullStage: { flex: 1, backgroundColor: '#000', overflow: 'hidden' },
  cover: { ...StyleSheet.absoluteFillObject, backgroundColor: '#000', alignItems: 'center', justifyContent: 'center' },
  bigPlay: { width: 76, height: 76, borderRadius: 38, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  skipPill: { width: 84, height: 84, borderRadius: 42, backgroundColor: 'rgba(255,255,255,0.14)', alignItems: 'center', justifyContent: 'center' },
  bufferingBadge: { position: 'absolute', top: 10, right: 10, width: 34, height: 34, borderRadius: 17, backgroundColor: 'rgba(0,0,0,0.55)', alignItems: 'center', justifyContent: 'center' },
  errorText: { color: COLORS.white, textAlign: 'center' },
  chip: { position: 'absolute', top: 10, left: 10, flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 10, paddingVertical: 5, borderRadius: RADIUS.full, backgroundColor: 'rgba(0,0,0,0.6)' },
  chipBehind: { backgroundColor: COLORS.gold },
  chipDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.live },
  chipText: { color: COLORS.white, fontSize: 11, lineHeight: 15, fontWeight: '800', letterSpacing: 0.6 },
  errorActions: { flexDirection: 'row', gap: 10, flexWrap: 'wrap', justifyContent: 'center' },
  controls: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'center', gap: 20, paddingVertical: 14 },
  buttonWrap: { alignItems: 'center', gap: 6, minWidth: 64 },
  button: { width: 54, height: 54, borderRadius: 27, backgroundColor: 'rgba(255,255,255,0.12)', alignItems: 'center', justifyContent: 'center' },
  buttonBig: { width: 62, height: 62, borderRadius: 31, backgroundColor: COLORS.primary },
  buttonSmall: { width: 48, height: 48, borderRadius: 24, backgroundColor: 'rgba(0,0,0,0.55)' },
  buttonSmallBig: { width: 58, height: 58, borderRadius: 29, backgroundColor: COLORS.primary },
  buttonLabel: { color: 'rgba(255,255,255,0.8)', fontSize: 11.5, lineHeight: 15, fontWeight: '600' },
  fullTop: { position: 'absolute', top: 14, right: 28, left: 28, alignItems: 'flex-end' },
  fullBar: { position: 'absolute', left: 0, right: 0, bottom: 18, alignItems: 'center' },
  fullBarInner: { flexDirection: 'row', alignItems: 'center', gap: 26, paddingHorizontal: 22, paddingVertical: 8, borderRadius: 40, backgroundColor: 'rgba(0,0,0,0.4)' },
});
