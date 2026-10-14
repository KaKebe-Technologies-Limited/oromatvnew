// Maps the Font Awesome icon names the website stores for each category to Ionicons names used in the app.
const ICONS = {
  'fa-folder': 'folder-outline',
  'fa-newspaper': 'newspaper-outline',
  'fa-landmark': 'business-outline',
  'fa-chart-line': 'trending-up-outline',
  'fa-futbol': 'football-outline',
  'fa-film': 'film-outline',
  'fa-laptop-code': 'laptop-outline',
  'fa-heart': 'heart-outline',
  'fa-heartbeat': 'pulse-outline',
  'fa-graduation-cap': 'school-outline',
  'fa-comment-dots': 'chatbubble-ellipses-outline',
  'fa-globe-africa': 'globe-outline',
  'fa-users': 'people-outline',
};

export const categoryIcon = (faName) => ICONS[faName] || 'folder-outline';
