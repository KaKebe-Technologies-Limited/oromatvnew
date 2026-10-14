// About screen: what Oroma TV is and what the app offers. Wording follows the website's own footer text.
import { ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../components/AppText';
import Button from '../components/Button';
import Logo from '../components/Logo';
import ScreenHeader from '../components/ScreenHeader';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { PUBLIC_SITE_URL } from '../lib/api';
import { openLink } from '../lib/links';

const FEATURES = [
  { icon: 'tv', title: 'Live TV', text: 'Watch the Oroma TV broadcast live, free, wherever you are.' },
  { icon: 'radio', title: 'Radio & channels', text: 'Tune in to the radio and extra video channels from the Live tab.' },
  { icon: 'film', title: 'Previous shows', text: 'Catch up on past broadcasts, interviews and special features on demand.' },
  { icon: 'newspaper', title: 'News & stories', text: 'Read the stories that matter to the Oroma community, and join the conversation in the comments.' },
];

export default function AboutScreen() {
  return (
    <View style={styles.screen}>
      <ScreenHeader title="About" />
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Logo height={96} />
          <AppText variant="title" style={{ marginTop: SPACING.sm }}>
            Oroma TV
          </AppText>
          <AppText style={styles.tagline}>“Dwon me Tumalo”</AppText>
          <AppText style={styles.lead}>Live TV, radio, and the stories that matter to the Oroma community — streaming free, anywhere in the world.</AppText>
        </View>

        <View style={styles.card}>
          {FEATURES.map((f, i) => (
            <View key={f.title} style={[styles.feature, i > 0 && styles.divider]}>
              <View style={styles.icon}>
                <Ionicons name={f.icon} size={20} color={COLORS.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <AppText variant="label">{f.title}</AppText>
                <AppText variant="caption" style={{ marginTop: 1 }}>
                  {f.text}
                </AppText>
              </View>
            </View>
          ))}
        </View>

        <View style={{ gap: 10 }}>
          <Button title="Visit oromatv.com" icon="globe" onPress={() => openLink(PUBLIC_SITE_URL)} />
          <Button title="Contact us" icon="mail" variant="outline" onPress={() => router.push('/contact')} />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, gap: SPACING.lg, paddingBottom: SPACING.xxl },
  hero: { alignItems: 'center', paddingVertical: SPACING.md },
  tagline: { color: COLORS.primary, fontStyle: 'italic', fontWeight: '600', marginTop: 2 },
  lead: { textAlign: 'center', marginTop: SPACING.sm + 4, paddingHorizontal: SPACING.md },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: SPACING.md, borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  feature: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14 },
  divider: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
  icon: { width: 40, height: 40, borderRadius: 20, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
});
