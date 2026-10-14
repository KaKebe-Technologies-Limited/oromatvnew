// Contact screen: send Oroma TV a message, a news tip or an advertising enquiry, plus the office details.
// The message goes to POST /api/v1/contact, which emails info@oromatv.com — the same as the website's contact form.
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import AppText from '../components/AppText';
import Button from '../components/Button';
import Chip from '../components/Chip';
import ScreenHeader from '../components/ScreenHeader';
import TextField from '../components/TextField';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { CONTACT, openExternal } from '../lib/links';

const SUBJECTS = ['General Inquiry', 'News Tip', 'Advertising', 'Technical Support', 'Other'];
const EMPTY = { name: '', email: '', subject: 'General Inquiry', message: '' };

function InfoRow({ icon, label, value, onPress }) {
  return (
    <Pressable accessibilityRole={onPress ? 'link' : 'text'} onPress={onPress} disabled={!onPress} style={styles.info}>
      <View style={styles.infoIcon}>
        <Ionicons name={icon} size={18} color={COLORS.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <AppText variant="caption">{label}</AppText>
        <AppText variant="label" style={onPress ? { color: COLORS.primary } : undefined}>
          {value}
        </AppText>
      </View>
    </Pressable>
  );
}

export default function ContactScreen() {
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState({ sending: false, sent: false, message: '' });

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const send = async () => {
    setStatus({ sending: true, sent: false, message: '' });
    setErrors({});
    try {
      const { data } = await api.post('/contact', form);
      setForm(EMPTY);
      setStatus({ sending: false, sent: true, message: data.message });
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      setStatus({ sending: false, sent: false, message: Object.keys(fields).length ? 'Please check the highlighted fields.' : errorMessage(e) });
    }
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Contact us" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false} contentContainerStyle={styles.content}>
          {status.sent ? (
            <View style={styles.success}>
              <View style={styles.successIcon}>
                <Ionicons name="checkmark" size={30} color={COLORS.success} />
              </View>
              <AppText variant="heading" style={{ textAlign: 'center' }}>
                Message sent
              </AppText>
              <AppText style={{ textAlign: 'center', marginTop: 4 }}>{status.message}</AppText>
              <Button title="Send another message" variant="outline" small onPress={() => setStatus({ sending: false, sent: false, message: '' })} style={{ marginTop: SPACING.md }} />
            </View>
          ) : (
            <View style={styles.card}>
              <AppText variant="subheading" style={{ marginBottom: SPACING.md }}>
                Send us a message
              </AppText>
              <TextField label="Your name" value={form.name} onChangeText={set('name')} error={errors.name} autoCapitalize="words" maxLength={100} />
              <TextField label="Email address" value={form.email} onChangeText={set('email')} error={errors.email} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} maxLength={150} />

              <AppText variant="label" style={{ marginBottom: 6 }}>
                Subject
              </AppText>
              <View style={styles.subjects}>
                {SUBJECTS.map((s) => (
                  <Chip key={s} label={s} selected={form.subject === s} onPress={() => set('subject')(s)} />
                ))}
              </View>
              {errors.subject ? (
                <AppText variant="caption" style={{ color: COLORS.danger, marginBottom: SPACING.sm }}>
                  {errors.subject}
                </AppText>
              ) : null}

              <TextField label="Message" value={form.message} onChangeText={set('message')} error={errors.message} multiline maxLength={3000} placeholder="Write your message here…" style={{ minHeight: 140 }} />

              {status.message ? (
                <AppText variant="caption" style={{ color: COLORS.danger, marginBottom: SPACING.sm }}>
                  {status.message}
                </AppText>
              ) : null}
              <Button title="Send message" icon="paper-plane" loading={status.sending} onPress={send} />
            </View>
          )}

          <View style={styles.card}>
            <AppText variant="subheading" style={{ marginBottom: SPACING.sm }}>
              Contact information
            </AppText>
            <InfoRow icon="mail" label="Email" value={CONTACT.email} onPress={() => openExternal(`mailto:${CONTACT.email}`)} />
            <InfoRow icon="call" label="Phone" value={CONTACT.phone} onPress={() => openExternal(CONTACT.phoneLink)} />
            <InfoRow icon="location" label="Address" value={CONTACT.address} />
            <InfoRow icon="time" label="Office hours" value={CONTACT.hours} />
          </View>

          <View style={styles.tip}>
            <Ionicons name="flash" size={24} color={COLORS.goldLight} />
            <View style={{ flex: 1 }}>
              <AppText variant="subheading" style={{ color: COLORS.white }}>
                Got a news tip?
              </AppText>
              <AppText style={{ color: 'rgba(255,255,255,0.8)', marginTop: 2 }}>Witnessing breaking news? Send us your story, photos or videos and help keep the community informed.</AppText>
              <Button title="Email tips@oromatv.com" variant="light" small onPress={() => openExternal(`mailto:${CONTACT.tips}`)} style={{ marginTop: SPACING.sm + 4, alignSelf: 'flex-start' }} />
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  subjects: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: SPACING.md },
  info: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  infoIcon: { width: 36, height: 36, borderRadius: 18, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  success: { alignItems: 'center', padding: SPACING.lg, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, ...SHADOW },
  successIcon: { width: 64, height: 64, borderRadius: 32, backgroundColor: '#E8F5E9', alignItems: 'center', justifyContent: 'center', marginBottom: SPACING.sm },
  tip: { flexDirection: 'row', gap: 14, padding: SPACING.md, borderRadius: RADIUS.lg, backgroundColor: COLORS.primary },
});
