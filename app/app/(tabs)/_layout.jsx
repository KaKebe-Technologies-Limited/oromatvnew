// Bottom tab bar: Home, News, Live (the round button in the middle), Shows and More. While Q FM plays, its small player bar
// sits just above the tabs.
import { View } from 'react-native';
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import AppTabBar from '../../components/AppTabBar';
import RadioMiniPlayer from '../../components/RadioMiniPlayer';
import { COLORS } from '../../constants/theme';

const icon = (name, focusedName) =>
  function TabIcon({ color, size, focused }) {
    return <Ionicons name={focused ? focusedName : name} size={size} color={color} />;
  };

export default function TabsLayout() {
  return (
    <Tabs
      tabBar={(props) => (
        <View>
          <RadioMiniPlayer />
          <AppTabBar {...props} />
        </View>
      )}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: COLORS.canvas } }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: icon('home-outline', 'home') }} />
      <Tabs.Screen name="news" options={{ title: 'News', tabBarIcon: icon('newspaper-outline', 'newspaper') }} />
      <Tabs.Screen name="live" options={{ title: 'Live' }} />
      <Tabs.Screen name="shows" options={{ title: 'Shows', tabBarIcon: icon('film-outline', 'film') }} />
      <Tabs.Screen name="more" options={{ title: 'More', tabBarIcon: icon('menu-outline', 'menu') }} />
    </Tabs>
  );
}
