// More tab: contact and about pages, the official social profiles, the website, and a connection check
// (handy when the app cannot reach the server).
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Constants from 'expo-constants';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { API_ROOT, PUBLIC_SITE_URL, errorMessage, ping } from '../../lib/api';
import { SOCIALS, openLink } from '../../lib/links';

function Row({ icon, title, subtitle, onPress, last }) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [styles.row, !last && styles.rowDivider, pressed && { backgroundColor: COLORS.gray100 }]}>
      <View style={styles.rowIcon}>
        <Ionicons name={icon} size={20} color={COLORS.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <AppText variant="label">{title}</AppText>
        {subtitle ? (
          <AppText variant="caption" style={{ marginTop: 1 }}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
    </Pressable>
  );
}

export default function MoreScreen() {
  const insets = useSafeAreaInsets();
  const [connection, setConnection] = useState({ state: 'idle', text: 'Tap to test the connection' });

  const testConnection = async () => {
    setConnection({ state: 'checking', text: 'Checking…' });
    try {
      const result = await ping();
      setConnection({ state: 'ok', text: `Connected — answered in ${result.ms} ms` });
    } catch (e) {
      setConnection({ state: 'failed', text: errorMessage(e) });
    }
  };

  return (
    <View style={styles.screen}>
      <View style={[styles.header, { paddingTop: insets.top + 10 }]}>
        <AppText variant="title">More</AppText>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.card}>
          <Row icon="mail" title="Contact us" subtitle="Send a message, a news tip or an advertising enquiry" onPress={() => router.push('/contact')} />
          <Row icon="information-circle" title="About Oroma TV" subtitle="Who we are and what is in this app" onPress={() => router.push('/about')} />
          <Row icon="globe" title="Visit oromatv.com" subtitle="Open the full website" onPress={() => openLink(PUBLIC_SITE_URL)} last />
        </View>

        <AppText variant="heading" style={styles.sectionTitle}>
          Follow Oroma TV
        </AppText>
        <View style={styles.socials}>
          {SOCIALS.map((s) => (
            <Pressable key={s.key} accessibilityRole="link" accessibilityLabel={s.label} onPress={() => openLink(s.url)} style={({ pressed }) => [styles.social, { backgroundColor: s.color }, pressed && { opacity: 0.85 }]}>
              <Ionicons name={s.icon} size={22} color={COLORS.white} />
              <AppText style={styles.socialText}>{s.label}</AppText>
            </Pressable>
          ))}
        </View>

        <AppText variant="heading" style={styles.sectionTitle}>
          App
        </AppText>
        <View style={styles.card}>
          <Pressable accessibilityRole="button" onPress={testConnection} style={({ pressed }) => [styles.row, styles.rowDivider, pressed && { backgroundColor: COLORS.gray100 }]}>
            <View style={styles.rowIcon}>
              <Ionicons name={connection.state === 'ok' ? 'checkmark-circle' : connection.state === 'failed' ? 'alert-circle' : 'wifi'} size={20} color={connection.state === 'ok' ? COLORS.success : connection.state === 'failed' ? COLORS.danger : COLORS.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <AppText variant="label">Server connection</AppText>
              <AppText variant="caption" numberOfLines={3} style={{ marginTop: 1, color: connection.state === 'failed' ? COLORS.danger : COLORS.gray700 }}>
                {connection.text}
              </AppText>
              <AppText variant="caption" numberOfLines={1} style={{ color: COLORS.gray500, marginTop: 1 }}>
                {API_ROOT}
              </AppText>
            </View>
          </Pressable>
          <View style={styles.row}>
            <View style={styles.rowIcon}>
              <Ionicons name="phone-portrait" size={20} color={COLORS.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <AppText variant="label">Version</AppText>
              <AppText variant="caption">{Constants.expoConfig?.version ?? '1.0.0'}</AppText>
            </View>
          </View>
        </View>

        <AppText variant="caption" style={styles.footer}>
          Oroma TV · “Dwon me Tumalo”{'\n'}Powered by Kakebe Technologies
        </AppText>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  header: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.sm + 4, backgroundColor: COLORS.white, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  content: { padding: SPACING.md, paddingBottom: SPACING.xl },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, overflow: 'hidden', borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: SPACING.md },
  rowDivider: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  rowIcon: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  sectionTitle: { marginTop: SPACING.lg, marginBottom: SPACING.sm + 4 },
  socials: { gap: 10 },
  social: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.md, height: 50, borderRadius: RADIUS.md },
  socialText: { color: COLORS.white, fontWeight: '600', fontSize: 15 },
  footer: { textAlign: 'center', marginTop: SPACING.xl, lineHeight: 19 },
});
