// Text with the Oroma TV typography (Montserrat headings, Inter body) applied through simple variants.
// A screen can pass a bigger fontSize. The variant's own line height would then be too short for the letters and some
// phones cut them off, so the line height grows with the text unless one is given.
import { StyleSheet, Text } from 'react-native';
import { COLORS, FONTS, MAX_FONT_SCALE } from '../constants/theme';

const VARIANTS = {
  display: { fontFamily: FONTS.headingXBold, fontSize: 28, lineHeight: 35, color: COLORS.black },
  title: { fontFamily: FONTS.headingBold, fontSize: 22, lineHeight: 29, color: COLORS.black },
  heading: { fontFamily: FONTS.headingBold, fontSize: 18, lineHeight: 24, color: COLORS.black },
  subheading: { fontFamily: FONTS.semibold, fontSize: 16, lineHeight: 22, color: COLORS.black },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  label: { fontFamily: FONTS.semibold, fontSize: 13, lineHeight: 18, color: COLORS.black },
  caption: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 17, color: COLORS.gray700 },
  overline: { fontFamily: FONTS.bold, fontSize: 11, lineHeight: 15, letterSpacing: 0.8, textTransform: 'uppercase', color: COLORS.primary },
};

export default function AppText({ variant = 'body', style, children, ...rest }) {
  const base = VARIANTS[variant] ?? VARIANTS.body;
  const own = StyleSheet.flatten(style) ?? {};
  const grown = own.fontSize && own.lineHeight === undefined && base.lineHeight < own.fontSize * 1.25 ? { lineHeight: Math.ceil(own.fontSize * 1.3) } : null;

  return (
    // A phone set to large text still grows the words a little, but not so much that buttons and cards look oversized or are cut off.
    <Text style={[base, style, grown]} maxFontSizeMultiplier={MAX_FONT_SCALE} {...rest}>
      {children}
    </Text>
  );
}
