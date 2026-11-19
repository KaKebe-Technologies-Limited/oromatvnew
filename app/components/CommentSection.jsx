// The comments under a story: the list, a Report and a Hide button on every comment, and a form to add one.
// Comments from the filter or a banned commenter are held for our team (the server answers "held"); every comment can be reported
// (we aim to review reports within 24 hours) and a reader can hide all comments that use a commenter's name on their own phone. Before
// the first post the reader agrees to the Terms of Use (no tolerance for objectionable content).
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import api, { badResponse, errorMessage, fieldErrors } from '../lib/api';
import { timeAgo, initials } from '../lib/format';
import { LEGAL, openLink } from '../lib/links';
import { useModeration } from '../lib/moderation';
import { getViewerId } from '../lib/presence';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';
import Button from './Button';
import CommentSheet from './CommentSheet';
import TextField from './TextField';

export default function CommentSection({ slug, comments, onPosted }) {
  const mod = useModeration();
  const [form, setForm] = useState({ name: '', email: '', comment: '' });
  const [errors, setErrors] = useState({});
  const [agreed, setAgreed] = useState(false);
  const [status, setStatus] = useState({ sending: false, message: '', failed: false, held: false });
  // The sheet keeps its comment while it slides out; `session` is new for every opening so its content starts fresh.
  const [sheet, setSheet] = useState({ open: false, type: null, comment: null, session: 0 });
  const openSheet = (type, comment) => setSheet((s) => ({ open: true, type, comment, session: s.session + 1 }));

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const list = comments ?? [];
  const visible = list.filter((c) => !mod.isHidden(c.name) && !mod.isReported(c.id));
  const hiddenFromCommenters = list.filter((c) => mod.isHidden(c.name)).length;
  const needsTick = mod.ready && !mod.termsAccepted; // the checkbox is shown until the first successful post (not before we know)

  const submit = async () => {
    if (needsTick && !agreed) {
      setErrors({ accepted_terms: 'Please tick the box to agree to the Terms of Use.' });
      return;
    }
    setStatus({ sending: true, message: '', failed: false, held: false });
    setErrors({});
    try {
      const res = await api.post(`/articles/${slug}/comments`, { ...form, viewer_id: await getViewerId(), accepted_terms: true });
      const body = res.data;
      if (!body || typeof body !== 'object') throw badResponse(); // a captive-portal or hosting page is not a posted comment
      mod.acceptTerms();
      setForm((f) => ({ ...f, comment: '' }));
      if (res.status === 202 || body.held) {
        // Held for a quick check: it is not shown yet, so tell the reader plainly.
        setStatus({ sending: false, message: body.message || 'Thanks! Your comment needs a quick check by our team before it appears.', failed: false, held: true });
        return;
      }
      if (body.data) onPosted?.(body.data);
      setStatus({ sending: false, message: body.message || 'Thanks! Your comment is now live.', failed: false, held: false });
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      setStatus({ sending: false, message: Object.keys(fields).length ? '' : errorMessage(e), failed: true, held: false });
    }
  };

  return (
    <View>
      <AppText variant="heading" style={styles.heading}>
        {visible.length} {visible.length === 1 ? 'Comment' : 'Comments'}
      </AppText>

      {visible.length === 0 ? (
        <AppText style={styles.empty}>Be the first to share your thoughts on this story.</AppText>
      ) : (
        visible.map((c) => (
          <View key={c.id} style={styles.item}>
            <View style={styles.avatar}>
              <AppText style={styles.avatarText}>{initials(c.name)}</AppText>
            </View>
            <View style={{ flex: 1 }}>
              <View style={styles.meta}>
                <AppText variant="label">{c.name}</AppText>
                <AppText variant="caption">{timeAgo(c.created_at)}</AppText>
              </View>
              <AppText style={styles.text}>{c.comment}</AppText>
              <View style={styles.actions}>
                <Pressable accessibilityRole="button" accessibilityLabel="Report this comment" onPress={() => openSheet('report', c)} style={styles.action}>
                  <Ionicons name="flag-outline" size={15} color={COLORS.gray700} />
                  <AppText variant="caption" style={styles.actionText}>
                    Report
                  </AppText>
                </Pressable>
                <Pressable accessibilityRole="button" accessibilityLabel={`Hide comments from ${c.name}`} onPress={() => openSheet('hide', c)} style={styles.action}>
                  <Ionicons name="eye-off-outline" size={15} color={COLORS.gray700} />
                  <AppText variant="caption" style={styles.actionText}>
                    Hide {c.name}
                  </AppText>
                </Pressable>
              </View>
            </View>
          </View>
        ))
      )}

      {hiddenFromCommenters > 0 ? (
        <Pressable accessibilityRole="link" onPress={() => router.push('/hidden-commenters')} style={styles.manage}>
          <AppText variant="caption">
            {hiddenFromCommenters} comment{hiddenFromCommenters === 1 ? '' : 's'} hidden from commenters you hid.{' '}
            <AppText variant="caption" style={styles.link}>
              Manage
            </AppText>
          </AppText>
        </Pressable>
      ) : null}

      <View style={styles.form}>
        <AppText variant="subheading" style={{ marginBottom: SPACING.md }}>
          Leave a comment
        </AppText>
        <TextField label="Your name" value={form.name} onChangeText={set('name')} error={errors.name} maxLength={100} autoCapitalize="words" returnKeyType="next" />
        <TextField label="Email (optional, never shown)" value={form.email} onChangeText={set('email')} error={errors.email} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} maxLength={150} />
        <TextField label="Comment" value={form.comment} onChangeText={set('comment')} error={errors.comment} multiline maxLength={2000} placeholder="Join the conversation…" />

        {needsTick ? (
          <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: agreed }} onPress={() => setAgreed((v) => !v)} style={styles.check}>
            <Ionicons name={agreed ? 'checkbox' : 'square-outline'} size={24} color={errors.accepted_terms ? COLORS.danger : COLORS.primary} />
            <AppText style={styles.checkText}>I agree to the Terms of Use and understand there is no tolerance for objectionable content or abusive users.</AppText>
          </Pressable>
        ) : null}
        {errors.accepted_terms ? (
          <AppText variant="caption" style={[styles.note, { color: COLORS.danger }]}>
            {errors.accepted_terms}
          </AppText>
        ) : null}

        <AppText variant="caption" style={styles.terms}>
          By posting you agree to our{' '}
          <AppText variant="caption" style={styles.link} onPress={() => openLink(LEGAL.terms)} accessibilityRole="link">
            Terms of Use
          </AppText>
          . We have no tolerance for objectionable content or abusive users. Comments can be reported. We aim to review reports within 24 hours.
        </AppText>

        {status.message ? (
          <AppText variant="caption" style={[styles.note, { color: status.failed ? COLORS.danger : status.held ? COLORS.warning : COLORS.success }]}>
            {status.message}
          </AppText>
        ) : null}
        <Button title="Post comment" icon="paper-plane" loading={status.sending} disabled={!mod.ready} onPress={submit} />
      </View>

      <CommentSheet visible={sheet.open} type={sheet.type} comment={sheet.comment} session={sheet.session} onClose={() => setSheet((s) => ({ ...s, open: false }))} mod={mod} />
    </View>
  );
}

const styles = StyleSheet.create({
  heading: { marginBottom: SPACING.md },
  manage: { minHeight: 44, justifyContent: 'center', marginBottom: SPACING.sm },
  empty: { color: COLORS.gray700, fontStyle: 'italic', marginBottom: SPACING.md },
  item: { flexDirection: 'row', gap: 12, marginBottom: SPACING.md },
  avatar: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: COLORS.white, fontFamily: FONTS.bold, fontSize: 14 },
  meta: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 2 },
  text: { fontSize: 14.5, lineHeight: 21 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', columnGap: 6, marginTop: 2 },
  action: { flexDirection: 'row', alignItems: 'center', gap: 5, minHeight: 44, paddingRight: 12 },
  actionText: { color: COLORS.gray700, fontFamily: FONTS.semibold },
  form: { marginTop: SPACING.md, padding: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border },
  check: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, minHeight: 44, marginBottom: SPACING.sm },
  checkText: { flex: 1, fontSize: 13.5, lineHeight: 19 },
  terms: { marginBottom: SPACING.sm, lineHeight: 17 },
  link: { color: COLORS.primary, fontFamily: FONTS.semibold, textDecorationLine: 'underline' },
  note: { marginBottom: SPACING.sm },
});
