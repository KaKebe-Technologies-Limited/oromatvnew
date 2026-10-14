// Desktop-browser twin of EmbedFrame.jsx (react-native-webview does not run on the web): a plain <iframe>.
// Only used when previewing the app with `npx expo start --web`.
import { createElement } from 'react';
import { View } from 'react-native';

export default function EmbedFrame({ src, style }) {
  return (
    <View style={[{ backgroundColor: '#000', overflow: 'hidden' }, style]}>
      {createElement('iframe', {
        src,
        title: 'Player',
        allow: 'autoplay; encrypted-media; fullscreen; picture-in-picture',
        allowFullScreen: true,
        style: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', border: 0 },
      })}
    </View>
  );
}
