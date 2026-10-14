// Formatting helpers for dates, counts and text used across the screens.

export function timeAgo(iso) {
  if (!iso) return '';
  const seconds = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} h ago`;
  if (seconds < 7 * 86400) return `${Math.floor(seconds / 86400)} d ago`;
  return formatDate(iso);
}

export function formatDate(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

const clock = (date) => date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** "Today, 6:00 PM" · "Tomorrow, 6:00 PM" · "Sat 17 Oct, 6:00 PM" — for a programme's start time. */
export function formatWhen(iso, skew = 0) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const now = new Date(Date.now() + skew);
  const tomorrow = new Date(now.getTime() + 86400000);
  if (sameDay(date, now)) return `Today, ${clock(date)}`;
  if (sameDay(date, tomorrow)) return `Tomorrow, ${clock(date)}`;
  return `${date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })}, ${clock(date)}`;
}

/**
 * "in 5 min" · "in 2h 15m" · "in 3 days" · "starting now" until a moment. `skew` is how far the server's clock is ahead of the
 * phone's, so the countdown is right even if the phone's clock is a little off.
 */
export function countdown(iso, skew = 0) {
  if (!iso) return '';
  const seconds = (new Date(iso).getTime() - (Date.now() + skew)) / 1000;
  if (seconds < 45) return 'starting now';
  if (seconds < 3600) return `in ${Math.max(1, Math.round(seconds / 60))} min`;
  if (seconds < 86400) {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return m === 60 ? `in ${h + 1}h` : `in ${h}h${m ? ` ${m}m` : ''}`;
  }
  const days = Math.round(seconds / 86400);
  return `in ${days} ${days === 1 ? 'day' : 'days'}`;
}

/** 1250 -> "1.2K", 3400000 -> "3.4M" for view counts. */
export function compactNumber(value) {
  const n = Number(value || 0);
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1).replace(/\.0$/, '')}K`;
  return String(n);
}

export function initials(name = '') {
  return (
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0].toUpperCase())
      .join('') || '?'
  );
}

/** "Good morning" / "Good afternoon" / "Good evening" for the viewer's local time. */
export function greeting(date = new Date()) {
  const hour = date.getHours();
  return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}
