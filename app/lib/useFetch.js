// Small data-fetching hook for GET endpoints. Returns { data, loading, refreshing, error, reload, retry }
// so every screen can show loading, error and empty states the same way.
import { useCallback, useEffect, useRef, useState } from 'react';
import api, { errorMessage } from './api';

export function useFetch(path, { params, enabled = true } = {}) {
  const [state, setState] = useState({ data: null, loading: enabled, refreshing: false, error: null });
  const latest = useRef(0);
  const paramsKey = JSON.stringify(params ?? {});

  const load = useCallback(
    async (mode = 'load') => {
      if (!enabled) return;
      const id = ++latest.current;
      // 'quiet' refreshes in the background (polling) without flashing a spinner or clearing an error banner.
      if (mode !== 'quiet') setState((s) => ({ ...s, loading: mode === 'load', refreshing: mode === 'refresh', error: null }));
      try {
        const { data } = await api.get(path, { params: JSON.parse(paramsKey) });
        if (id === latest.current) setState({ data, loading: false, refreshing: false, error: null });
      } catch (e) {
        if (id !== latest.current) return;
        // A failed background refresh keeps showing what we already have.
        if (mode === 'quiet') return;
        setState((s) => ({ ...s, loading: false, refreshing: false, error: errorMessage(e) }));
      }
    },
    [path, paramsKey, enabled],
  );

  useEffect(() => {
    load('load');
    return () => {
      latest.current++;
    };
  }, [load]);

  // Stable functions, so timers and effects that depend on them do not restart on every render.
  const reload = useCallback(() => load('refresh'), [load]);
  const retry = useCallback(() => load('load'), [load]);
  const refreshQuietly = useCallback(() => load('quiet'), [load]);

  return { ...state, reload, retry, refreshQuietly };
}
