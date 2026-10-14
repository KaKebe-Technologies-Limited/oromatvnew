// The browser inside a video player: a WebView that shows the page from lib/youtubePage.js, passes the player's messages up
// (onMessage) and lets the parent send commands down (ref.run('toggle')). The desktop-browser twin is PlayerSurface.web.jsx.
import { forwardRef, useImperativeHandle, useRef } from 'react';
import { StyleSheet } from 'react-native';
import { WebView } from 'react-native-webview';

const PlayerSurface = forwardRef(function PlayerSurface({ html, baseUrl, onMessage, onFailed, style }, ref) {
  const web = useRef(null);

  useImperativeHandle(ref, () => ({
    run: (command) => web.current?.injectJavaScript(`window.__cmd && window.__cmd(${JSON.stringify(command)}); true;`),
  }));

  return (
    <WebView
      ref={web}
      source={{ html, baseUrl }}
      style={[styles.web, style]}
      originWhitelist={['*']}
      javaScriptEnabled
      domStorageEnabled
      scrollEnabled={false}
      bounces={false}
      allowsInlineMediaPlayback
      mediaPlaybackRequiresUserAction={false}
      mixedContentMode="always"
      setSupportMultipleWindows={false}
      onMessage={(e) => {
        try {
          onMessage(JSON.parse(e.nativeEvent.data));
        } catch {
          // Not one of ours.
        }
      }}
      onError={onFailed}
    />
  );
});

export default PlayerSurface;

const styles = StyleSheet.create({
  web: { flex: 1, backgroundColor: '#000' },
});
