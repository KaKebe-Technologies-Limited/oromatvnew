// The comments under a story: the list, and a form to add one. Comments go live straight away, exactly like the website.
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { timeAgo, initials } from '../lib/format';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';
import Button from './Button';
import TextField from './TextField';

export default function CommentSection({ slug, comments, onPosted }) {
  const [form, setForm] = useState({ name: '', email: '', comment: '' });
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState({ sending: false, message: '', failed: false });

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const submit = async () => {
    setStatus({ sending: true, message: '', failed: false });
    setErrors({});
    try {
      const { data } = await api.post(`/articles/${slug}/comments`, form);
      if (data.data) onPosted(data.data);
      setForm((f) => ({ ...f, comment: '' }));
      setStatus({ sending: false, message: data.message || 'Thanks! Your comment is now live.', failed: false });
    } catch (e) {
      setErrors(fieldErrors(e));
      setStatus({ sending: false, message: Object.keys(fieldErrors(e)).length ? '' : errorMessage(e), failed: true });
    }
  };

  return (
    <View>
      <AppText variant="heading" style={styles.heading}>
        {comments.length} {comments.length === 1 ? 'Comment' : 'Comments'}
      </AppText>

      {comments.length === 0 ? (
        <AppText style={styles.empty}>Be the first to share your thoughts on this story.</AppText>
      ) : (
        comments.map((c) => (
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
            </View>
          </View>
        ))
      )}

      <View style={styles.form}>
        <AppText variant="subheading" style={{ marginBottom: SPACING.md }}>
          Leave a comment
        </AppText>
        <TextField label="Your name" value={form.name} onChangeText={set('name')} error={errors.name} maxLength={100} autoCapitalize="words" returnKeyType="next" />
        <TextField label="Email (optional, never shown)" value={form.email} onChangeText={set('email')} error={errors.email} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} maxLength={150} />
        <TextField label="Comment" value={form.comment} onChangeText={set('comment')} error={errors.comment} multiline maxLength={2000} placeholder="Join the conversation…" />
        {status.message ? (
          <AppText variant="caption" style={[styles.note, { color: status.failed ? COLORS.danger : COLORS.success }]}>
            {status.message}
          </AppText>
        ) : null}
        <Button title="Post comment" icon="paper-plane" loading={status.sending} onPress={submit} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  heading: { marginBottom: SPACING.md },
  empty: { color: COLORS.gray700, fontStyle: 'italic', marginBottom: SPACING.md },
  item: { flexDirection: 'row', gap: 12, marginBottom: SPACING.md },
  avatar: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: COLORS.white, fontFamily: FONTS.bold, fontSize: 14 },
  meta: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 2 },
  text: { fontSize: 14.5, lineHeight: 21 },
  form: { marginTop: SPACING.md, padding: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border },
  note: { marginBottom: SPACING.sm },
});
