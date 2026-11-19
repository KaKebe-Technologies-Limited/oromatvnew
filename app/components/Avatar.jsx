// A round picture of a person: their photo when there is one, otherwise their initials on the brand colour (or a plain person icon
// when there is no name yet). Used in More, on the profile screen and next to comments. A photo that cannot be loaded falls back to the initials.
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { initials } from '../lib/format';
import AppText from './AppText';

/** `decorative` hides it from screen readers where the person's name is read right next to it (the comment list). */
export default function Avatar({ name = '', uri = null, size = 40, decorative = false, style }) {
  const [failed, setFailed] = useState(false);
  const source = uri ? mediaUrl(uri) : null;
  useEffect(() => setFailed(false), [source]); // a new picture gets its own chance to load

  const shown = !!source && !failed;
  const hasName = String(name || '').trim() !== '';
  const label = shown ? `Photo of ${hasName ? name : 'you'}` : hasName ? `${name}, no photo` : 'No photo';
  const box = { width: size, height: size, borderRadius: size / 2 };
  const hidden = decorative ? { accessible: false, accessibilityElementsHidden: true, importantForAccessibility: 'no-hide-descendants' } : { accessible: true, accessibilityRole: 'image', accessibilityLabel: label };

  return (
    <View style={[styles.base, box, shown && styles.withPhoto, style]} {...hidden}>
      {shown ? (
        <Image source={{ uri: source }} style={box} contentFit="cover" transition={150} onError={() => setFailed(true)} recyclingKey={source} />
      ) : hasName ? (
        <AppText style={[styles.initials, { fontSize: Math.max(11, Math.round(size * 0.38)) }]} maxFontSizeMultiplier={1}>
          {initials(String(name))}
        </AppText>
      ) : (
        <Ionicons name="person" size={Math.round(size * 0.52)} color={COLORS.white} />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  base: { backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  withPhoto: { backgroundColor: COLORS.gray100 },
  initials: { color: COLORS.white, fontFamily: FONTS.bold },
});
