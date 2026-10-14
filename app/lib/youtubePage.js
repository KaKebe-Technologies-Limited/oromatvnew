// Builds the small web page that plays a YouTube video with YouTube's own controls switched off, using YouTube's official
// IFrame Player API (controls=0). The app draws its own Back / Stop / Forward buttons and tells this page what to do
// through window.__cmd('back' | 'toggle' | 'play' | 'stop' | 'forward' | 'live' | 'mute' | 'unmute'); the page reports what the player is doing back to
// the app as JSON messages: { type: 'ready' } · { type: 'state', state } · { type: 'error', code } · { type: 'tick', t }
// (the position in seconds, once a second while playing — so a video can carry on from the same moment after going fullscreen)
// · and, for a synchronized programme, { type: 'sync', lag } (how many seconds the viewer is behind everyone else).
//
// A synchronized programme (a recording that every viewer watches at the same moment, like TV) passes `syncStart`: the moment
// the programme began, on THIS phone's clock (the server's start time minus how far the phone's clock is off). The page then
// starts the video at the right second, and quietly pulls the viewer back to it if they drift. Pressing Back or Stop leaves
// the shared moment on purpose ("behind"); `live` — the Jump to live button — returns to it.
// Used by components/PlayerSurface.jsx (a WebView on phones) and PlayerSurface.web.jsx (an iframe in a desktop browser).

export const SKIP_SECONDS = 10;

export function youtubePage(videoId, { autoplay = true, start = 0, crop = 0, syncStart = 0, behind = false, muted = false } = {}) {
  return `<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
  html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
  #wrap { position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; background: #000; }
  /* The video is touched only through the app's own buttons, never by YouTube's player UI. */
  iframe { position: absolute; left: 0; top: -${crop}px; width: 100%; height: calc(100% + ${crop * 2}px); border: 0; pointer-events: none; }
</style>
</head>
<body>
<div id="wrap"><div id="p"></div></div>
<script>
  var player = null;
  var ready = false;
  var syncStart = ${Math.round(syncStart) || 0}; // 0 = a normal video; otherwise when the programme began (phone clock, ms)
  var behind = ${behind ? 'true' : 'false'}; // the viewer chose to leave the shared moment (pressed Back or Stop)
  function expected() { return Math.max(0, (Date.now() - syncStart) / 1000); }
  var NAMES = { '-1': 'unstarted', '0': 'ended', '1': 'playing', '2': 'paused', '3': 'buffering', '5': 'cued' };

  function send(message) {
    var text = JSON.stringify(message);
    if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(text);
    else window.parent.postMessage(text, '*');
  }

  window.onYouTubeIframeAPIReady = function () {
    var vars = { autoplay: ${autoplay ? 1 : 0}, controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3, modestbranding: 1, playsinline: 1, rel: 0, cc_load_policy: 0 };
    if (/^https?:/.test(location.origin)) vars.origin = location.origin;
    ${start > 0 ? `vars.start = ${Math.floor(start)};` : ''}
    if (syncStart && !behind) vars.start = Math.floor(expected()); // join the programme where everyone else is
    player = new YT.Player('p', {
      videoId: ${JSON.stringify(String(videoId))},
      playerVars: vars,
      events: {
        onReady: function () {
          ready = true;
          send({ type: 'ready' });
          if (syncStart && !behind) player.seekTo(expected(), true); // the page took a moment to load; catch up
          ${muted ? 'player.mute();' : ''}
          ${autoplay ? 'player.playVideo();' : ''}
        },
        onStateChange: function (e) { send({ type: 'state', state: NAMES[e.data] || 'unknown' }); },
        onError: function (e) { send({ type: 'error', code: e.data }); }
      }
    });
  };

  setInterval(function () {
    if (ready && player.getPlayerState() === 1) send({ type: 'tick', t: player.getCurrentTime() });
  }, 1000);

  // Synchronized programmes: report how far behind the shared moment we are, and pull a drifting viewer back to it.
  setInterval(function () {
    if (!ready || !syncStart) return;
    var lag = expected() - (player.getCurrentTime() || 0);
    send({ type: 'sync', lag: lag });
    if (!behind && player.getPlayerState() === 1 && Math.abs(lag) > 5) player.seekTo(expected(), true);
  }, 2000);

  window.__cmd = function (command) {
    if (!player || !ready) return;
    var now = player.getCurrentTime() || 0;
    if (command === 'play') player.playVideo();
    else if (command === 'mute') player.mute(); // the radio is playing: keep the picture running, silently
    else if (command === 'unmute') player.unMute();
    else if (command === 'stop') { if (syncStart) behind = true; player.pauseVideo(); }
    else if (command === 'toggle') { if (player.getPlayerState() === 1) { if (syncStart) behind = true; player.pauseVideo(); } else player.playVideo(); }
    else if (command === 'live') { behind = false; player.seekTo(expected(), true); player.playVideo(); }
    else if (command === 'back') { if (syncStart) behind = true; player.seekTo(Math.max(0, now - ${SKIP_SECONDS}), true); }
    else if (command === 'forward') {
      var end = player.getDuration() || 0; // for a live stream this is the live edge, so we never skip past "now"
      var target = end > 0 ? Math.min(now + ${SKIP_SECONDS}, Math.max(0, end - 1)) : now + ${SKIP_SECONDS};
      if (syncStart) { // never past the shared moment; reaching it again rejoins everyone
        target = Math.min(target, expected());
        if (expected() - target < 3) behind = false;
      }
      player.seekTo(target, true);
    }
  };
</script>
<script src="https://www.youtube.com/iframe_api"></script>
</body>
</html>`;
}
