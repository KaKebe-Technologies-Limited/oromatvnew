// The bottom sheet for the two things a reader can do about someone else's comment: Report it (a reason, then it goes to our team;
// we aim to review reports within 24 hours) or Hide everything from that commenter on this phone. It is a Modal rather than an Alert so
// it looks the same on Android and iPhone and has no three-button limit.
// The parent gives every opening a new `session`, which rebuilds the sheet's content from scratch, so a "Thank you" or an error from
// the last comment is never shown on the next one; the comment stays in the parent while the sheet slides out.
import { useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api, { badResponse, errorCode, errorMessage } from '../lib/api';
import { CONTACT, openExternal } from '../lib/links';
import { getViewerId } from '../lib/presence';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';
import Button from './Button';

const REASONS = [
  { key: 'spam', label: 'Spam or advertising', icon: 'megaphone-outline' },
  { key: 'abusive', label: 'Abusive, hateful or threatening', icon: 'warning-outline' },
  { key: 'misleading', label: 'Misleading or false', icon: 'help-circle-outline' },
  { key: 'other', label: 'Something else', icon: 'ellipsis-horizontal-circle-outline' },
];

const REVIEW_PROMISE = 'We aim to review reports within 24 hours.';
const SEND_FAILED = 'We could not send your report. Please try again.';

function SheetBody({ type, comment, onClose, mod }) {
  const insets = useSafeAreaInsets();
  const [sending, setSending] = useState(null); // the reason being sent
  const [outcome, setOutcome] = useState(null); // null | 'sent' | 'gone'
  const [error, setError] = useState('');

  const report = async (reason) => {
    setSending(reason);
    setError('');
    try {
      const res = await api.post(`/comments/${comment.id}/report`, { reason, viewer_id: await getViewerId(), platform: 'app' });
      if (!res.data || typeof res.data !== 'object') throw badResponse(); // a captive-portal or hosting page is not a received report
      mod.markReported(comment.id);
      setOutcome('sent');
    } catch (e) {
      if (e?.response?.status === 404 && errorCode(e) === 'comment_gone') {
        // The server says this comment no longer exists or is not public: nothing more to report, so it can leave the list.
        mod.markReported(comment.id);
        setOutcome('gone');
      } else {
        // Any other failure, including a plain 404 from a server that does not have the report route yet, was NOT recorded.
        setError(e?.response?.status === 429 ? errorMessage(e, SEND_FAILED) : SEND_FAILED);
      }
    } finally {
      setSending(null);
    }
  };

  const hide = () => {
    mod.hideName(comment.name);
    onClose();
  };

  return (
    <View style={[styles.sheet, { paddingBottom: Math.max(SPACING.lg, insets.bottom + SPACING.sm) }]} accessibilityViewIsModal>
      <View style={styles.grip} />

      {type === 'report' ? (
        outcome ? (
          <View style={styles.center}>
            <Ionicons name={outcome === 'sent' ? 'checkmark-circle' : 'information-circle'} size={44} color={outcome === 'sent' ? COLORS.success : COLORS.gray700} />
            <AppText variant="heading" style={styles.title}>
              {outcome === 'sent' ? 'Thank you' : 'No longer available'}
            </AppText>
            <AppText style={styles.body}>{outcome === 'sent' ? `We have received your report. ${REVIEW_PROMISE}` : 'That comment is no longer available, so there is nothing more to report.'}</AppText>
            <Button title="Done" onPress={onClose} style={{ alignSelf: 'stretch', marginTop: SPACING.md }} />
          </View>
        ) : (
          <View>
            <AppText variant="heading" style={styles.title}>
              Report this comment
            </AppText>
            <AppText style={styles.body}>Why are you reporting {comment.name}’s comment?</AppText>
            {REASONS.map((r) => (
              <Pressable key={r.key} accessibilityRole="button" accessibilityLabel={r.label} disabled={!!sending} onPress={() => report(r.key)} style={({ pressed }) => [styles.reason, pressed && { backgroundColor: COLORS.gray100 }, sending && sending !== r.key && { opacity: 0.45 }]}>
                <Ionicons name={r.icon} size={20} color={COLORS.primary} />
                <AppText style={styles.reasonText}>{r.label}</AppText>
                {sending === r.key ? <ActivityIndicator color={COLORS.primary} /> : <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />}
              </Pressable>
            ))}
            {error ? <AppText style={styles.error}>{error}</AppText> : null}
          </View>
        )
      ) : (
        <View>
          <AppText variant="heading" style={styles.title}>
            Hide comments from {comment.name}?
          </AppText>
          <AppText style={styles.body}>You will no longer see comments from this name on this phone. You can undo this any time in More › Hidden commenters. If someone is abusive, please also use Report so our team can act.</AppText>
          <Button title={`Hide ${comment.name}`} icon="eye-off" onPress={hide} style={{ marginTop: SPACING.md }} />
          <Button title="Cancel" variant="outline" onPress={onClose} style={{ marginTop: SPACING.sm }} />
        </View>
      )}

      <Pressable accessibilityRole="link" onPress={() => openExternal(`mailto:${CONTACT.email}`)} style={styles.footer}>
        <AppText variant="caption" style={{ textAlign: 'center' }}>
          Need to reach us directly? <AppText variant="caption" style={{ color: COLORS.primary, fontFamily: FONTS.semibold }}>{CONTACT.email}</AppText>
        </AppText>
      </Pressable>
    </View>
  );
}

export default function CommentSheet({ visible, type, comment, session, onClose, mod }) {
  if (!comment) return null;

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent navigationBarTranslucent>
      <Pressable style={styles.backdrop} onPress={onClose} accessibilityRole="button" accessibilityLabel="Close" />
      <SheetBody key={session} type={type} comment={comment} onClose={onClose} mod={mod} />
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(11,18,32,0.55)' },
  sheet: { backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, paddingHorizontal: SPACING.md, paddingTop: SPACING.sm },
  grip: { alignSelf: 'center', width: 42, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, marginBottom: SPACING.md },
  center: { alignItems: 'center' },
  title: { marginBottom: 6 },
  body: { color: COLORS.gray900, marginBottom: SPACING.sm + 4, lineHeight: 22 },
  reason: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 54, paddingHorizontal: SPACING.sm + 4, borderRadius: RADIUS.md, borderWidth: 1, borderColor: COLORS.border, marginBottom: 8 },
  reasonText: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14.5 },
  error: { color: COLORS.danger, marginTop: 4 },
  footer: { marginTop: SPACING.sm, minHeight: 44, justifyContent: 'center' },
});
