// The Oroma TV logo (the website's trimmed logo image), sized by height.
import { Image } from 'expo-image';

const SOURCE = require('../assets/logo.png');
const RATIO = 361 / 201; // width / height of the image

export default function Logo({ height = 40, style }) {
  return <Image source={SOURCE} contentFit="contain" accessibilityLabel="Oroma TV" style={[{ height, width: height * RATIO }, style]} />;
}
