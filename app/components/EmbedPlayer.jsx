// A web player that is not YouTube (for example a Dacast stream), shown at 16:9 with a Fullscreen button in its corner.
// Fullscreen turns the phone sideways and shows the player edge to edge; the exit button brings the phone back upright.
// (YouTube videos use components/YouTubePlayer.jsx instead, which has its own buttons.)
import { useEffect, useState } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { COLORS } from '../constants/theme';
import { lockLandscape, lockPortrait } from '../lib/orientation';
import EmbedFrame from './EmbedFrame';

function CornerButton({ name, label, onPress, style }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} hitSlop={8} style={({ pressed }) => [styles.corner, style, pressed && { opacity: 0.8 }]}>
      <MaterialCommunityIcons name={name} size={24} color={COLORS.white} />
    </Pressable>
  );
}

export default function EmbedPlayer({ src, referrer, height, fullscreenable = true, style }) {
  const [full, setFull] = useState(false);

  useEffect(() => () => void lockPortrait(), []);

  const enter = async () => {
    await lockLandscape();
    setFull(true);
  };
  const exit = async () => {
    setFull(false);
    await lockPortrait();
  };

  return (
    <View style={[styles.stage, height ? { height } : { aspectRatio: 16 / 9 }, style]}>
      {full ? null : <EmbedFrame src={src} referrer={referrer} style={StyleSheet.absoluteFill} />}
      {fullscreenable && !full ? <CornerButton name="fullscreen" label="Fullscreen" onPress={enter} style={styles.corner_br} /> : null}

      <Modal visible={full} animationType="fade" statusBarTranslucent navigationBarTranslucent supportedOrientations={['landscape', 'landscape-left', 'landscape-right', 'portrait']} onRequestClose={exit}>
        <StatusBar hidden />
        <View style={styles.full}>
          <EmbedFrame src={src} referrer={referrer} style={StyleSheet.absoluteFill} />
          <CornerButton name="fullscreen-exit" label="Exit fullscreen" onPress={exit} style={styles.corner_tr} />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  stage: { width: '100%', backgroundColor: '#000', overflow: 'hidden' },
  full: { flex: 1, backgroundColor: '#000' },
  corner: { position: 'absolute', width: 42, height: 42, borderRadius: 21, backgroundColor: 'rgba(0,0,0,0.55)', alignItems: 'center', justifyContent: 'center' },
  corner_br: { right: 10, bottom: 10 },
  corner_tr: { right: 28, top: 14 },
});
