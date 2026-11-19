// Reads app.json and adds the settings that depend on the kind of build.
//
// Plain http:// is blocked in an installed app on both phones. A test build that talks to the API on a computer over the home
// Wi-Fi (the "local-wifi" build in eas.json) turns that block off with ALLOW_CLEARTEXT=1 (Android: cleartext traffic; iPhone: App
// Transport Security); the real "production" builds never do, so they only ever talk to the live server over https.
// Store builds (EXPO_PUBLIC_APP_ENV=production) also leave out the development API address.
//
// Android push notifications need the Firebase file google-services.json. It is added only when it exists (at ./google-services.json,
// or where GOOGLE_SERVICES_JSON points, which is how EAS passes a secret file), so a build without Firebase still succeeds; the app
// then simply cannot get a push token on Android and says so (see lib/notifications.js).
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  const cleartext = process.env.ALLOW_CLEARTEXT === '1';
  const production = process.env.EXPO_PUBLIC_APP_ENV === 'production';
  const apiUrl = { ...(config.extra?.apiUrl ?? {}) };
  if (production) delete apiUrl.development;

  const googleServicesFile = process.env.GOOGLE_SERVICES_JSON || './google-services.json';
  const hasGoogleServices = fs.existsSync(path.resolve(__dirname, googleServicesFile));

  return {
    ...config,
    ios: {
      ...config.ios,
      infoPlist: {
        ...(config.ios?.infoPlist ?? {}),
        NSAppTransportSecurity: { NSAllowsArbitraryLoads: cleartext },
      },
    },
    android: {
      ...config.android,
      ...(hasGoogleServices ? { googleServicesFile } : {}),
    },
    extra: { ...config.extra, apiUrl },
    plugins: [...(config.plugins ?? []), ['expo-build-properties', { android: { usesCleartextTraffic: cleartext } }]],
  };
};
