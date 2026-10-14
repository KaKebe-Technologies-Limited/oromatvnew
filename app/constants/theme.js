// Oroma TV brand tokens for the mobile app: colours, fonts, spacing, radii and shadow.
// Mirrors the website's design system (maroon #800000 + gold, Montserrat headings, Inter body) in a mobile layout:
// a soft light canvas with white rounded cards and a dark "ink" navy for the player and tab bar accents.

export const COLORS = {
  primary: '#800000',
  primaryDark: '#5A0000',
  primaryLight: '#A00000',
  primaryMist: '#F8ECEC',
  gold: '#C9A84C',
  goldLight: '#E2C46E',
  ink: '#0B1220',
  black: '#111827',
  gray900: '#374151',
  gray700: '#6B7280',
  gray500: '#9CA3AF',
  gray300: '#D1D5DB',
  gray100: '#F3F4F6',
  white: '#FFFFFF',
  success: '#15803D',
  warning: '#B45309',
  danger: '#DC2626',
  info: '#1D4ED8',
  // The red of the "LIVE" badge.
  live: '#E11D1D',
  border: '#E8EAEE',
  // Screen background behind the white cards.
  canvas: '#F6F7F9',
};

export const FONTS = {
  regular: 'Inter_400Regular',
  medium: 'Inter_500Medium',
  semibold: 'Inter_600SemiBold',
  bold: 'Inter_700Bold',
  headingBold: 'Montserrat_700Bold',
  headingXBold: 'Montserrat_800ExtraBold',
};

// A phone set to a large font still grows our words, but never past this multiple of their size,
// so nothing looks oversized or gets cut off.
export const MAX_FONT_SCALE = 1.15;

export const SPACING = { xs: 4, sm: 8, md: 16, lg: 24, xl: 32, xxl: 48 };

export const RADIUS = { sm: 8, md: 12, lg: 16, xl: 24, full: 9999 };

export const SHADOW = {
  shadowColor: '#101828',
  shadowOpacity: 0.08,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 5 },
  elevation: 3,
};

// Used until a category's own colour arrives from the API (same palette the website uses).
export const CATEGORY_FALLBACK = '#800000';
