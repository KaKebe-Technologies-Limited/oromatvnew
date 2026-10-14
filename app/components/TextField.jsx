// A labelled text input with an inline error message, used by the comment and contact forms.
import { StyleSheet, TextInput, View } from 'react-native';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function TextField({ label, error, multiline = false, style, ...rest }) {
  return (
    <View style={styles.wrap}>
      {label ? (
        <AppText variant="label" style={styles.label}>
          {label}
        </AppText>
      ) : null}
      <TextInput
        placeholderTextColor={COLORS.gray500}
        multiline={multiline}
        textAlignVertical={multiline ? 'top' : 'center'}
        maxFontSizeMultiplier={1.15}
        style={[styles.input, multiline && styles.multiline, !!error && styles.invalid, style]}
        {...rest}
      />
      {error ? (
        <AppText variant="caption" style={styles.error}>
          {error}
        </AppText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: SPACING.md },
  label: { marginBottom: 6 },
  input: {
    minHeight: 48,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1.5,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    fontFamily: FONTS.regular,
    fontSize: 15,
    color: COLORS.black,
  },
  multiline: { minHeight: 110, paddingTop: 12 },
  invalid: { borderColor: COLORS.danger },
  error: { color: COLORS.danger, marginTop: 4 },
});
