// Shows an embedded web player (the live stream, a YouTube video, a radio player) inside the app.
// The embed address is wrapped in a small page that is loaded as if it were on oromatv.com (see lib/embeds.js).
// The desktop-browser twin of this file is EmbedFrame.web.jsx.
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { COLORS, SPACING } from '../constants/theme';
import { PUBLIC_SITE_URL } from '../lib/api';
import { embedPage } from '../lib/embeds';
import AppText from './AppText';
import Button from './Button';

export default function EmbedFrame({ src, referrer = PUBLIC_SITE_URL, style }) {
  const [state, setState] = useState({ loading: true, failed: false, attempt: 0 });

  return (
    <View style={[styles.box, style]}>
      {state.failed ? (
        <View style={styles.center}>
          <AppText style={styles.failText}>The player could not be loaded. Check your connection.</AppText>
          <Button title="Try again" variant="light" small onPress={() => setState({ loading: true, failed: false, attempt: state.attempt + 1 })} />
        </View>
      ) : (
        <WebView
          key={state.attempt}
          source={{ html: embedPage(src), baseUrl: referrer }}
          style={styles.web}
          originWhitelist={['*']}
          javaScriptEnabled
          domStorageEnabled
          allowsFullscreenVideo
          allowsInlineMediaPlayback
          mediaPlaybackRequiresUserAction={false}
          mixedContentMode="always"
          setSupportMultipleWindows={false}
          onLoadEnd={() => setState((s) => ({ ...s, loading: false }))}
          onError={() => setState((s) => ({ ...s, loading: false, failed: true }))}
        />
      )}
      {state.loading && !state.failed ? (
        <View style={styles.center} pointerEvents="none">
          <ActivityIndicator color={COLORS.white} />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: { backgroundColor: '#000', overflow: 'hidden' },
  web: { flex: 1, backgroundColor: '#000' },
  center: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', gap: SPACING.sm, padding: SPACING.lg },
  failText: { color: COLORS.white, textAlign: 'center' },
});
