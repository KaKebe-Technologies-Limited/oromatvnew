// Renders an article's HTML (the website's sanitised editor output) inside a WebView that grows to fit its content,
// so the story scrolls together with the rest of the screen. Links open outside the article, in the in-app browser.
// The desktop-browser twin of this file is ArticleBody.web.jsx.
import { useMemo, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { PUBLIC_SITE_URL } from '../lib/api';
import { articleCss } from '../lib/articleHtml';
import { openLink } from '../lib/links';

function page(html, font) {
  return `<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
  html, body { margin: 0; padding: 0; background: transparent; }
  ${articleCss(font)}
</style>
</head>
<body>
<div id="c">${html}</div>
<script>
  (function () {
    var box = document.getElementById('c');
    function send() {
      window.ReactNativeWebView.postMessage(JSON.stringify({ height: Math.ceil(box.getBoundingClientRect().height) }));
    }
    window.addEventListener('load', send);
    if (window.ResizeObserver) new ResizeObserver(send).observe(box);
    send();
  })();
</script>
</body>
</html>`;
}

export default function ArticleBody({ html, font = 'inter' }) {
  const [height, setHeight] = useState(240);
  const source = useMemo(() => ({ html: page(html, font), baseUrl: PUBLIC_SITE_URL }), [html, font]);

  return (
    <View style={{ height }}>
      <WebView
        source={source}
        style={styles.web}
        originWhitelist={['*']}
        scrollEnabled={false}
        showsVerticalScrollIndicator={false}
        javaScriptEnabled
        mixedContentMode="always"
        setSupportMultipleWindows={false}
        onMessage={(e) => {
          try {
            const next = JSON.parse(e.nativeEvent.data).height;
            if (next > 0) setHeight((current) => (Math.abs(current - next) > 1 ? next : current));
          } catch {
            // Ignore messages that are not ours.
          }
        }}
        // Taps on links leave the article; the page's own first load (about:blank / the base address) is allowed.
        onShouldStartLoadWithRequest={(request) => {
          const url = request.url || '';
          if (url === 'about:blank' || url.startsWith('data:') || url.replace(/\/$/, '') === PUBLIC_SITE_URL) return true;
          openLink(url);
          return false;
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  web: { flex: 1, backgroundColor: 'transparent' },
});
