// Everything the Live screen needs, kept up to date by itself: the on-air stage, what is next, the off-air playlist
// and the channels (GET /live). While `active` (the Live tab is on screen) it re-checks every 30 seconds, and again at the
// exact moment a programme is due to start or end — so a scheduled programme appears on screen the second it begins.
import { useEffect } from 'react';
import { setLiveInfo } from './liveStatus';
import { useFetch } from './useFetch';

const CHECK_EVERY_MS = 30000;
const MAX_WAIT_MS = 24 * 3600 * 1000;

export function useLiveFeed(active = true) {
  const feed = useFetch('/live');
  const live = feed.data?.data ?? null;
  const skew = live?.server_time ? Date.parse(live.server_time) - Date.now() : 0;
  const { refreshQuietly } = feed;

  // Share what we learned with the tab bar and the home card.
  useEffect(() => {
    if (live) setLiveInfo({ status: live.status, title: live.stage?.title, next: live.next, server_time: live.server_time });
  }, [live]);

  // Regular check while the screen is showing.
  useEffect(() => {
    if (!active) return undefined;
    const timer = setInterval(refreshQuietly, CHECK_EVERY_MS);
    return () => clearInterval(timer);
  }, [active, refreshQuietly]);

  // Precise check when the next programme starts or the current one is due to end.
  useEffect(() => {
    if (!active || !live) return undefined;
    const due = [live.next?.starts_at, live.stage?.effective_end]
      .filter(Boolean)
      .map((iso) => Date.parse(iso) - (Date.now() + skew))
      .filter((ms) => ms > -1000 && ms < MAX_WAIT_MS);
    if (!due.length) return undefined;
    const timer = setTimeout(refreshQuietly, Math.max(0, Math.min(...due)) + 1500);
    return () => clearTimeout(timer);
  }, [active, live, skew, refreshQuietly]);

  return { ...feed, live, skew };
}
