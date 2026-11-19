// Reads app.json and adds the settings that depend on the kind of build.
//
// Plain http:// is blocked in an installed app on both phones. A test build that talks to the API on a computer over the home
// Wi-Fi (the "local-wifi" build in eas.json) turns that block off with ALLOW_CLEARTEXT=1 (Android: cleartext traffic; iPhone: App
// Transport Security); the real "production" builds never do, so they only ever talk to the live server over https.
// Store builds (EXPO_PUBLIC_APP_ENV=production) also leave out the development API address.
module.exports = ({ config }) => {
  const cleartext = process.env.ALLOW_CLEARTEXT === '1';
  const production = process.env.EXPO_PUBLIC_APP_ENV === 'production';
  const apiUrl = { ...(config.extra?.apiUrl ?? {}) };
  if (production) delete apiUrl.development;

  return {
    ...config,
    ios: {
      ...config.ios,
      infoPlist: {
        ...(config.ios?.infoPlist ?? {}),
        NSAppTransportSecurity: { NSAllowsArbitraryLoads: cleartext },
      },
    },
    extra: { ...config.extra, apiUrl },
    plugins: [...(config.plugins ?? []), ['expo-build-properties', { android: { usesCleartextTraffic: cleartext } }]],
  };
};
