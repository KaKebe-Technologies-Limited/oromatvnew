// My profile: the optional details a person can save to get updates from Oroma TV (photo, name, email, phone, and which updates they want).
// There is no login: the profile belongs to this phone (see lib/profile.js). Everything is optional except a display name once anything is saved.
// A change is only sent when the person taps Save, except the photo, which is uploaded as soon as it is picked (once there is a profile).
// The photo is checked by our team before anyone else sees it; the status under it tells the person where it stands.
import { useEffect, useRef, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import Avatar from '../components/Avatar';
import Button from '../components/Button';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import TextField from '../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { LEGAL, openLink } from '../lib/links';
import { enablePushNotifications, getNotificationPermission, openSystemSettings } from '../lib/notifications';
import { useProfile } from '../lib/profile';

const TOPICS = [
  { key: 'breaking', label: 'Breaking news' },
  { key: 'live', label: 'Live broadcasts and premieres' },
  { key: 'shows', label: 'New shows and movies' },
];
const ALL_TOPICS = { breaking: true, live: true, shows: true };
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MIN_PHOTO_PIXELS = 128;
const MAX_PHOTO_BYTES = 4 * 1024 * 1024;
const FIELD_ERRORS = ['display_name', 'email', 'phone', 'accepted_terms', 'consent_email'];

/** Ask "are you sure?". An Alert does nothing in a desktop browser, so the browser's own question is used there. */
function confirm(title, message, confirmLabel) {
  if (Platform.OS === 'web') return Promise.resolve(typeof globalThis.confirm === 'function' ? globalThis.confirm(`${title}\n\n${message}`) : false);
  return new Promise((resolve) => {
    Alert.alert(
      title,
      message,
      [
        { text: 'Cancel', style: 'cancel', onPress: () => resolve(false) },
        { text: confirmLabel, style: 'destructive', onPress: () => resolve(true) },
      ],
      { cancelable: true, onDismiss: () => resolve(false) },
    );
  });
}

const NOTE_LOOK = {
  success: { icon: 'checkmark-circle', color: COLORS.success },
  error: { icon: 'alert-circle', color: COLORS.danger },
  warning: { icon: 'information-circle', color: COLORS.warning },
  info: { icon: 'information-circle', color: COLORS.gray700 },
};

/** A short message with an icon. Screen readers read it out when it appears. */
function Note({ note, style }) {
  if (!note?.text) return null;
  const look = NOTE_LOOK[note.kind] ?? NOTE_LOOK.info;
  return (
    <View style={[styles.note, style]} accessibilityRole={note.kind === 'error' ? 'alert' : undefined} accessibilityLiveRegion="polite">
      <Ionicons name={look.icon} size={18} color={look.color} style={{ marginTop: 1 }} />
      <AppText variant="caption" style={[styles.noteText, { color: look.color }]}>
        {note.text}
      </AppText>
    </View>
  );
}

function Card({ title, children, style }) {
  return (
    <View style={[styles.card, style]}>
      {title ? (
        <AppText variant="subheading" style={styles.cardTitle} accessibilityRole="header">
          {title}
        </AppText>
      ) : null}
      {children}
    </View>
  );
}

/** A caption right under the field above it (the field leaves a gap that the negative margin takes back). */
const Help = ({ children }) => (
  <AppText variant="caption" style={styles.help}>
    {children}
  </AppText>
);

/** A whole-row switch. The switch itself is only for show: the row is the one control, so it is one tap target and one thing for a screen reader. */
function SwitchRow({ title, caption, value, onChange, disabled = false, loading = false }) {
  const inactive = disabled || loading;
  return (
    <Pressable
      accessibilityRole="switch"
      accessibilityLabel={title}
      accessibilityHint={caption}
      accessibilityState={{ checked: value, disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={() => onChange(!value)}
      style={({ pressed }) => [styles.switchRow, pressed && { backgroundColor: COLORS.gray100 }, disabled && { opacity: 0.55 }]}
    >
      <View style={{ flex: 1 }}>
        <AppText variant="subheading">{title}</AppText>
        {caption ? (
          <AppText variant="caption" style={{ marginTop: 2 }}>
            {caption}
          </AppText>
        ) : null}
      </View>
      <View style={{ pointerEvents: 'none' }} aria-hidden importantForAccessibility="no-hide-descendants">
        <Switch value={value} disabled={inactive} trackColor={{ false: COLORS.gray300, true: COLORS.primary }} thumbColor={COLORS.white} />
      </View>
    </Pressable>
  );
}

function CheckRow({ checked, onChange, children, label, error }) {
  return (
    <Pressable accessibilityRole="checkbox" accessibilityLabel={label} accessibilityState={{ checked }} onPress={() => onChange(!checked)} style={styles.check}>
      <Ionicons name={checked ? 'checkbox' : 'square-outline'} size={24} color={error ? COLORS.danger : COLORS.primary} />
      <View style={{ flex: 1 }}>{children}</View>
    </Pressable>
  );
}

function PhotoStatus({ status, reason, waitingToUpload }) {
  let look = null;
  if (waitingToUpload) look = { icon: 'cloud-upload-outline', color: COLORS.gray900, bg: COLORS.gray100, text: 'Ready. It will be uploaded when you save.' };
  else if (status === 'pending') look = { icon: 'time-outline', color: COLORS.warning, bg: '#FEF3E2', text: 'Waiting for approval' };
  else if (status === 'approved') look = { icon: 'checkmark-circle', color: COLORS.success, bg: '#E8F5E9', text: 'Approved' };
  else if (status === 'rejected') look = { icon: 'close-circle', color: COLORS.danger, bg: '#FDECEC', text: reason ? `Not approved: ${reason}` : 'Not approved' };
  if (!look) return null;
  return (
    <View style={[styles.badge, { backgroundColor: look.bg }]} accessible accessibilityLabel={`Photo status: ${look.text}`}>
      <Ionicons name={look.icon} size={16} color={look.color} />
      <AppText variant="caption" style={{ color: look.color, fontFamily: FONTS.semibold, flexShrink: 1 }}>
        {look.text}
      </AppText>
    </View>
  );
}

const PHOTO_HINT = {
  none: 'Add a photo of yourself. Our team checks it before it appears next to your comments.',
  pending: 'Only you can see this photo until our team approves it.',
  approved: 'This photo is shown next to your comments.',
  rejected: 'Please choose a different photo.',
};

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const p = useProfile();
  const { profile, hasProfile } = p;
  const scrollRef = useRef(null);
  const emailRef = useRef(null);
  const phoneRef = useRef(null);

  const [edits, setEdits] = useState({}); // only what the person has changed since the last save
  const [topicEdits, setTopicEdits] = useState({});
  const [terms, setTerms] = useState(false);
  const [photoLocal, setPhotoLocal] = useState(null); // a photo picked before the profile exists: uploaded right after it is made
  const [pushGot, setPushGot] = useState(null); // the push token obtained when the switch was turned on; sent when Save is tapped
  const [pushIssue, setPushIssue] = useState(null); // { reason, message } when notifications could not be switched on
  const [permission, setPermission] = useState('undetermined');
  const [errors, setErrors] = useState({});
  const [notice, setNotice] = useState(null); // the result of Save
  const [photoNote, setPhotoNote] = useState(null);
  const [emailNote, setEmailNote] = useState(null);
  const [busy, setBusy] = useState(''); // '' | 'photo' | 'push' | 'resend' | 'delete'

  // The saved profile is shown at once; fetching it again picks up what changed since (photo approved, email confirmed).
  useEffect(() => {
    if (p.ready && hasProfile) p.refresh({ maxAgeMs: 15000 });
  }, [p.ready, hasProfile]);

  // If notifications were switched off for the app in the phone's settings, say so (the system never asks again by itself).
  const pushSaved = !!(profile?.consent_push && profile?.push_registered);
  useEffect(() => {
    let cancelled = false;
    if (pushSaved) getNotificationPermission().then((state) => !cancelled && setPermission(state));
    return () => {
      cancelled = true;
    };
  }, [pushSaved]);

  const base = {
    display_name: profile?.display_name ?? '',
    email: profile?.email ?? '',
    phone: profile?.phone ?? '',
    consent_email: !!profile?.consent_email,
    push: pushSaved, // "on" means the server also has this phone's token (it drops a token that stopped working)
    topics: profile?.topics ?? ALL_TOPICS,
  };
  const value = { ...base, ...edits, topics: { ...base.topics, ...topicEdits } };
  const name = value.display_name.trim();
  const emailValue = value.email.trim().toLowerCase();
  const hasEmail = emailValue !== '';
  const emailOn = hasEmail && value.consent_email;
  const changed = {
    display_name: name !== base.display_name,
    email: emailValue !== base.email,
    phone: value.phone.trim() !== base.phone,
    topics: TOPICS.some((t) => value.topics[t.key] !== base.topics[t.key]),
    consent_email: emailOn !== base.consent_email,
    push: value.push !== base.push,
  };
  const dirty = Object.values(changed).some(Boolean);
  const channelOn = emailOn || value.push;
  const working = p.saving || !!busy;

  const edit = (key) => (text) => {
    setEdits((e) => ({ ...e, [key]: text }));
    setErrors((e) => ({ ...e, [key]: undefined }));
    setNotice(null);
  };
  const editTopic = (key) => (on) => {
    setTopicEdits((t) => ({ ...t, [key]: on }));
    setNotice(null);
  };

  const showFailure = (res) => {
    const fields = Object.fromEntries(FIELD_ERRORS.filter((k) => res.fields?.[k]).map((k) => [k, res.fields[k]]));
    setErrors(fields);
    setNotice({ kind: 'error', text: Object.keys(fields).length ? 'Please check the highlighted fields.' : res.message });
    if (fields.display_name || fields.email || fields.phone) scrollRef.current?.scrollTo({ y: 0, animated: true });
    if (res.code === 'bad_token') {
      // The server no longer knows this phone's profile (the saved copy is already gone): keep what was typed so it can be made again.
      setEdits({ display_name: value.display_name, email: value.email, phone: value.phone, consent_email: value.consent_email });
      setTopicEdits(value.topics);
      setPushGot(null);
    }
  };

  const onSave = async () => {
    if (working) return;
    setNotice(null);
    const found = {};
    const length = Array.from(name).length;
    if (length < 2 || length > 40) found.display_name = 'Please enter a name of 2 to 40 characters.';
    if (hasEmail && !EMAIL_RE.test(emailValue)) found.email = 'Please enter a valid email address, or leave it blank.';
    if (!hasProfile && !terms) found.accepted_terms = 'Please tick the box to agree to the Terms of Use.';
    if (Object.keys(found).length) {
      setErrors(found);
      setNotice({ kind: 'error', text: 'Please check the highlighted fields.' });
      if (found.display_name || found.email) scrollRef.current?.scrollTo({ y: 0, animated: true });
      return;
    }
    setErrors({});

    const problems = []; // what failed after the profile itself was saved
    let res;
    if (!hasProfile) {
      const fields = { display_name: name, accepted_terms: true, topics: value.topics, consent_email: emailOn };
      if (hasEmail) fields.email = emailValue;
      if (value.phone.trim()) fields.phone = value.phone.trim();
      res = await p.create(fields);
      if (!res.ok) return showFailure(res);
      if (value.push) {
        const pushed = await p.registerPush(pushGot);
        if (!pushed.ok) problems.push(`Notifications could not be switched on: ${pushed.message}`);
      }
      if (photoLocal) {
        const sent = await p.uploadPhoto(photoLocal);
        if (!sent.ok) problems.push(`Your photo could not be uploaded: ${sent.message}`);
        setPhotoLocal(null);
      }
    } else {
      const patch = {};
      if (changed.display_name) patch.display_name = name;
      if (changed.email) patch.email = emailValue;
      if (changed.phone) patch.phone = value.phone.trim();
      if (changed.topics) patch.topics = value.topics;
      if (changed.consent_email) patch.consent_email = emailOn;
      res = Object.keys(patch).length ? await p.update(patch) : { ok: true };
      if (!res.ok) return showFailure(res);
      if (changed.push) {
        const pushed = value.push ? await p.registerPush(pushGot) : await p.unregisterPush();
        if (!pushed.ok) problems.push(`Notifications could not be switched ${value.push ? 'on' : 'off'}: ${pushed.message}`);
      }
    }

    const emailSet = hasEmail && (!hasProfile || changed.email);
    setEdits({});
    setTopicEdits({});
    setTerms(false);
    setPushGot(null);
    setPushIssue(null);
    setEmailNote(null);
    setNotice({ kind: problems.length ? 'warning' : 'success', text: ['Your profile has been saved.', emailSet ? `We sent a confirmation link to ${emailValue}.` : '', ...problems].filter(Boolean).join(' ') });
  };

  const pickPhoto = async () => {
    if (working) return;
    setPhotoNote(null);
    let result;
    try {
      result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.7, exif: false });
    } catch {
      setPhotoNote({ kind: 'error', text: 'We could not open your photos. Please try again.' });
      return;
    }
    const asset = result.canceled ? null : result.assets?.[0];
    if (!asset) return;
    if (asset.fileSize > MAX_PHOTO_BYTES) {
      setPhotoNote({ kind: 'error', text: 'That photo is too large. Please choose one under 4 MB.' });
      return;
    }
    if (asset.width && asset.height && Math.min(asset.width, asset.height) < MIN_PHOTO_PIXELS) {
      setPhotoNote({ kind: 'error', text: `That photo is too small. Please choose one at least ${MIN_PHOTO_PIXELS} pixels wide and high.` });
      return;
    }
    if (!hasProfile) {
      setPhotoLocal(asset); // there is nowhere to upload it yet
      return;
    }
    setBusy('photo');
    const res = await p.uploadPhoto(asset);
    setBusy('');
    setPhotoNote(res.ok ? { kind: 'success', text: 'Photo uploaded. It is waiting for approval.' } : { kind: 'error', text: res.message });
  };

  const removePhoto = async () => {
    if (working) return;
    setPhotoNote(null);
    if (!hasProfile) {
      setPhotoLocal(null);
      return;
    }
    setBusy('photo');
    const res = await p.removePhoto();
    setBusy('');
    setPhotoNote(res.ok ? { kind: 'success', text: 'Photo removed.' } : { kind: 'error', text: res.message });
  };

  const onPush = async (on) => {
    setNotice(null);
    setPushIssue(null);
    if (!on) {
      setEdits((e) => ({ ...e, push: false }));
      setPushGot(null);
      return;
    }
    if (base.push) {
      setEdits((e) => ({ ...e, push: true })); // it was already on: nothing to ask the phone
      return;
    }
    setBusy('push');
    const res = await enablePushNotifications(); // the only place the system is asked for permission
    setBusy('');
    if (!res.ok) {
      setPushIssue({ reason: res.reason, message: res.message });
      return;
    }
    setPushGot(res);
    setEdits((e) => ({ ...e, push: true }));
  };

  const onEmailUpdates = (on) => {
    setEdits((e) => ({ ...e, consent_email: on }));
    setErrors((e) => ({ ...e, consent_email: undefined }));
    setNotice(null);
  };

  const resend = async () => {
    if (working) return;
    setEmailNote(null);
    setBusy('resend');
    const res = await p.resendEmail();
    setBusy('');
    setEmailNote(res.ok ? { kind: 'success', text: res.message } : { kind: 'error', text: res.message });
  };

  const onDelete = async () => {
    if (working) return;
    const sure = await confirm('Delete your profile?', 'This removes your profile from our server and from this phone. Your published comments stay, without your picture. This cannot be undone.', 'Delete my profile');
    if (!sure) return;
    setBusy('delete');
    const res = await p.deleteProfile();
    setBusy('');
    if (res.ok) {
      setEdits({});
      setTopicEdits({});
      setTerms(false);
      setPhotoLocal(null);
      setPushGot(null);
      setPushIssue(null);
      setErrors({});
      setPhotoNote(null);
      setEmailNote(null);
      setNotice({ kind: 'success', text: res.message });
      scrollRef.current?.scrollTo({ y: 0, animated: false });
    } else {
      setNotice({ kind: 'error', text: res.message });
    }
  };

  const waiting = hasProfile && !profile && !p.error; // the saved copy is not on the phone yet and the first answer has not come
  const photoStatus = hasProfile ? profile?.photo_status ?? 'none' : 'none';
  const photoUri = photoLocal ? photoLocal.uri : profile?.photo_url || null;
  const hasPhoto = !!photoLocal || (hasProfile && photoStatus !== 'none');

  return (
    <View style={styles.screen}>
      <ScreenHeader title="My profile" />
      <StateView loading={p.loading || waiting} error={hasProfile && !profile ? p.error : null} onRetry={() => p.refresh()}>
        <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
          <ScrollView ref={scrollRef} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false} contentContainerStyle={[styles.content, { paddingBottom: SPACING.xxl + insets.bottom }]}>
            {hasProfile && profile && p.error ? (
              <View style={styles.offline}>
                <Note note={{ kind: 'info', text: `${p.error} Showing what is saved on this phone.` }} style={{ flex: 1 }} />
                <Button title="Try again" variant="outline" small onPress={() => p.refresh()} style={styles.minTap} />
              </View>
            ) : null}

            {!hasProfile ? (
              <AppText style={styles.intro}>Add your details to get news alerts and updates from Oroma TV, and to show your name and photo with your comments. It is optional: you do not need an account to use the app.</AppText>
            ) : null}

            <Card>
              <View style={styles.photoBox}>
                <Avatar name={name} uri={photoUri} size={112} />
                <PhotoStatus status={photoStatus} reason={profile?.photo_reject_reason} waitingToUpload={!!photoLocal} />
                <AppText variant="caption" style={styles.photoHint}>
                  {photoLocal ? 'Our team checks it before it appears next to your comments.' : PHOTO_HINT[photoStatus] ?? PHOTO_HINT.none}
                </AppText>
                <View style={styles.photoButtons}>
                  <Button title={hasPhoto ? 'Change photo' : 'Choose photo'} icon="image" small loading={busy === 'photo'} disabled={working && busy !== 'photo'} onPress={pickPhoto} style={styles.minTap} />
                  {hasPhoto ? <Button title="Remove photo" icon="trash-outline" variant="outline" small disabled={working} onPress={removePhoto} style={styles.minTap} /> : null}
                </View>
                <Note note={photoNote} style={{ alignSelf: 'stretch', marginTop: SPACING.sm }} />
              </View>
            </Card>

            <Card title="Your details">
              <TextField
                label="Display name"
                value={value.display_name}
                onChangeText={edit('display_name')}
                error={errors.display_name}
                maxLength={40}
                autoCapitalize="words"
                autoComplete="name"
                textContentType="name"
                returnKeyType="next"
                onSubmitEditing={() => emailRef.current?.focus()}
                accessibilityHint="Between 2 and 40 characters. Shown with your comments."
              />
              <Help>Shown with your comments. 2 to 40 characters.</Help>

              <TextField
                ref={emailRef}
                label="Email (optional)"
                value={value.email}
                onChangeText={edit('email')}
                error={errors.email}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="email"
                textContentType="emailAddress"
                maxLength={190}
                returnKeyType="next"
                onSubmitEditing={() => phoneRef.current?.focus()}
              />
              {hasProfile && profile?.email && !changed.email ? (
                profile.email_verified ? (
                  <View style={styles.status} accessible accessibilityLabel="Email confirmed">
                    <Ionicons name="checkmark-circle" size={18} color={COLORS.success} />
                    <AppText variant="caption" style={{ color: COLORS.success, fontFamily: FONTS.semibold }}>
                      Confirmed
                    </AppText>
                  </View>
                ) : (
                  <View style={styles.confirm}>
                    <View style={styles.status}>
                      <Ionicons name="mail-unread-outline" size={18} color={COLORS.warning} />
                      <AppText variant="caption" style={{ color: COLORS.warning, fontFamily: FONTS.semibold, flexShrink: 1 }}>
                        Confirm your email: check your inbox
                      </AppText>
                    </View>
                    <Button title="Resend" variant="outline" small loading={busy === 'resend'} disabled={working && busy !== 'resend'} onPress={resend} style={styles.minTap} />
                  </View>
                )
              ) : hasEmail && changed.email ? (
                <Help>We will send a confirmation link to this address when you save.</Help>
              ) : null}
              <Note note={emailNote} style={{ marginBottom: SPACING.md }} />

              <TextField
                ref={phoneRef}
                label="Phone (optional)"
                value={value.phone}
                onChangeText={edit('phone')}
                error={errors.phone}
                keyboardType="phone-pad"
                autoComplete="tel"
                textContentType="telephoneNumber"
                maxLength={24}
                placeholder="0772 033 333"
              />
              <Help>Only Oroma TV staff can see your phone number.</Help>
            </Card>

            <Card title="Updates">
              <AppText variant="caption" style={styles.cardIntro}>
                Choose how you want to hear from us. Nothing is switched on unless you do it, and you can switch it off any time.
              </AppText>
              <SwitchRow title="Send me news alerts on this phone" caption="Your phone will ask for permission first." value={value.push} onChange={onPush} loading={busy === 'push'} />
              {pushIssue ? (
                <View style={styles.issue}>
                  <Note note={{ kind: 'error', text: pushIssue.message }} />
                  {pushIssue.reason === 'denied' ? <Button title="Open Settings" variant="outline" small onPress={openSystemSettings} style={[styles.minTap, { alignSelf: 'flex-start', marginTop: SPACING.xs }]} /> : null}
                </View>
              ) : null}
              {pushSaved && permission === 'denied' && value.push ? (
                <View style={styles.issue}>
                  <Note note={{ kind: 'warning', text: 'Notifications are switched off for Oroma TV in your phone settings, so you will not see alerts.' }} />
                  <Button title="Open Settings" variant="outline" small onPress={openSystemSettings} style={[styles.minTap, { alignSelf: 'flex-start', marginTop: SPACING.xs }]} />
                </View>
              ) : null}

              <View style={styles.divider} />
              <SwitchRow
                title="Email me news and programme updates"
                caption={hasEmail ? (profile?.email_verified && !changed.email ? 'You can unsubscribe from any email.' : 'You will start getting emails once you confirm your address.') : 'Add your email address above to switch this on.'}
                value={emailOn}
                onChange={onEmailUpdates}
                disabled={!hasEmail}
              />
              {errors.consent_email ? (
                <AppText variant="caption" style={[styles.help, { color: COLORS.danger, marginTop: 0 }]}>
                  {errors.consent_email}
                </AppText>
              ) : null}

              {channelOn ? (
                <View style={styles.topics}>
                  <View style={styles.divider} />
                  <AppText variant="label" style={{ marginBottom: 2 }}>
                    What do you want to hear about?
                  </AppText>
                  {TOPICS.map((t) => (
                    <CheckRow key={t.key} label={t.label} checked={!!value.topics[t.key]} onChange={editTopic(t.key)}>
                      <AppText>{t.label}</AppText>
                    </CheckRow>
                  ))}
                  {!TOPICS.some((t) => value.topics[t.key]) ? (
                    <AppText variant="caption" style={{ color: COLORS.warning }}>
                      Tick at least one topic to get updates.
                    </AppText>
                  ) : null}
                </View>
              ) : null}
            </Card>

            <Card>
              {!hasProfile ? (
                <CheckRow label="I agree to the Terms of Use" checked={terms} error={!!errors.accepted_terms} onChange={(on) => { setTerms(on); setErrors((e) => ({ ...e, accepted_terms: undefined })); setNotice(null); }}>
                  <AppText>
                    I agree to the{' '}
                    <AppText style={styles.link} accessibilityRole="link" onPress={() => openLink(LEGAL.terms)}>
                      Terms of Use
                    </AppText>
                  </AppText>
                </CheckRow>
              ) : null}
              {errors.accepted_terms ? (
                <AppText variant="caption" style={{ color: COLORS.danger, marginBottom: SPACING.sm }}>
                  {errors.accepted_terms}
                </AppText>
              ) : null}
              <AppText variant="caption" style={styles.legal}>
                You must be at least 13 years old to have a profile. We use your details only to show your name and photo with your comments and to send the updates you choose.{' '}
                <AppText variant="caption" style={styles.link} accessibilityRole="link" onPress={() => openLink(LEGAL.privacy)}>
                  Privacy Policy
                </AppText>
                {hasProfile ? (
                  <>
                    {' · '}
                    <AppText variant="caption" style={styles.link} accessibilityRole="link" onPress={() => openLink(LEGAL.terms)}>
                      Terms of Use
                    </AppText>
                  </>
                ) : null}
              </AppText>
            </Card>

            <Note note={notice} />
            <Button title={hasProfile ? 'Save changes' : 'Save profile'} icon="checkmark" loading={p.saving && !busy} disabled={(hasProfile && !dirty) || (working && !p.saving)} onPress={onSave} />

            {hasProfile ? (
              <View style={styles.danger}>
                <Pressable accessibilityRole="button" accessibilityLabel="Delete my profile" accessibilityState={{ disabled: working, busy: busy === 'delete' }} disabled={working} onPress={onDelete} style={({ pressed }) => [styles.deleteButton, pressed && { opacity: 0.8 }, working && { opacity: 0.55 }]}>
                  <Ionicons name="trash-outline" size={18} color={COLORS.danger} />
                  <AppText style={styles.deleteText}>Delete my profile</AppText>
                </Pressable>
                <AppText variant="caption" style={{ textAlign: 'center', marginTop: SPACING.sm }}>
                  This removes your profile from our server and from this phone. Your published comments stay, without your picture.
                </AppText>
              </View>
            ) : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, gap: SPACING.md },
  intro: { color: COLORS.gray900, lineHeight: 22 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, borderWidth: StyleSheet.hairlineWidth, borderColor: COLORS.border, ...SHADOW },
  cardTitle: { marginBottom: SPACING.md },
  cardIntro: { marginBottom: SPACING.sm, lineHeight: 18 },
  minTap: { minHeight: 44 },
  photoBox: { alignItems: 'center', gap: SPACING.sm },
  photoHint: { textAlign: 'center' },
  photoButtons: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 10, marginTop: SPACING.xs },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingVertical: 6, borderRadius: RADIUS.full, maxWidth: '100%' },
  note: { flexDirection: 'row', alignItems: 'flex-start', gap: 6 },
  noteText: { flex: 1 },
  offline: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  help: { marginTop: -SPACING.md + 2, marginBottom: SPACING.md },
  status: { flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1 },
  confirm: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginTop: -SPACING.md + 4, marginBottom: SPACING.md },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 56, paddingVertical: 8, borderRadius: RADIUS.md },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: COLORS.border, marginVertical: SPACING.sm },
  issue: { marginBottom: SPACING.xs },
  topics: { gap: 0 },
  check: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, minHeight: 44, paddingVertical: 8 },
  legal: { lineHeight: 18 },
  link: { color: COLORS.primary, fontFamily: FONTS.semibold, textDecorationLine: 'underline' },
  danger: { marginTop: SPACING.md, paddingTop: SPACING.lg, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
  deleteButton: { minHeight: 50, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: COLORS.danger, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: SPACING.lg },
  deleteText: { color: COLORS.danger, fontFamily: FONTS.semibold, fontSize: 15 },
});
