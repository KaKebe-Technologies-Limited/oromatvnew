// Builds the tiny HTML page that wraps an embed address (the live player, a YouTube video, a radio player).
// The wrapper is loaded with the website's address as its base, so the player believes it is on oromatv.com —
// that is what lets the live stream and YouTube accept being shown inside the app.

const escapeAttr = (value) => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

export function embedPage(src) {
  return `<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
  html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
  iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
</style>
</head>
<body>
<iframe src="${escapeAttr(src)}" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen webkitallowfullscreen mozallowfullscreen scrolling="no" referrerpolicy="origin"></iframe>
</body>
</html>`;
}
