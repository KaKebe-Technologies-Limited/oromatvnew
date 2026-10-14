// Desktop-browser twin of PlayerSurface.jsx (react-native-webview does not run on the web): the same page in an <iframe>.
// Only used when previewing the app with `npx expo start --web`.
import { createElement, forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import { View } from 'react-native';

const PlayerSurface = forwardRef(function PlayerSurface({ html, onMessage, style }, ref) {
  const frame = useRef(null);

  useImperativeHandle(ref, () => ({
    run: (command) => frame.current?.contentWindow?.__cmd?.(command),
  }));

  useEffect(() => {
    const listen = (event) => {
      if (event.source !== frame.current?.contentWindow || typeof event.data !== 'string') return;
      try {
        onMessage(JSON.parse(event.data));
      } catch {
        // Not one of ours.
      }
    };
    window.addEventListener('message', listen);
    return () => window.removeEventListener('message', listen);
  }, [onMessage]);

  return (
    <View style={[{ backgroundColor: '#000', overflow: 'hidden', flex: 1 }, style]}>
      {createElement('iframe', {
        ref: frame,
        srcDoc: html,
        title: 'Player',
        allow: 'autoplay; encrypted-media',
        style: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', border: 0 },
      })}
    </View>
  );
});

export default PlayerSurface;
