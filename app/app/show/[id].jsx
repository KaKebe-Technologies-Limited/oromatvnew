// Show screen: plays a previous episode (YouTube) right inside the app, with its guest, date, and more episodes below.
import { FlatList, ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import SectionHeader from '../../components/SectionHeader';
import ShowCard from '../../components/ShowCard';
import StateView from '../../components/StateView';
import YouTubePlayer from '../../components/YouTubePlayer';
import { COLORS, SPACING } from '../../constants/theme';
import { formatDate } from '../../lib/format';
import { shareLink } from '../../lib/links';
import { useFetch } from '../../lib/useFetch';

export default function ShowScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/shows/${id}`);
  const show = data?.data;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Previous show" dark />
      <StateView loading={loading} error={error} onRetry={retry}>
        {show ? (
          <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: SPACING.xxl }}>
            {/* key = show id, so tapping a related episode loads a fresh player rather than reusing the old one. */}
            <YouTubePlayer key={show.id} videoId={show.video_id} watchUrl={show.watch_url} />

            <View style={styles.info}>
              {show.guest || show.kind === 'movie' ? (
                <AppText variant="overline" style={{ color: COLORS.goldLight }}>
                  {[show.kind === 'movie' ? 'Movie' : null, show.guest].filter(Boolean).join(' · ')}
                </AppText>
              ) : null}
              <AppText variant="title" style={styles.title}>
                {show.title}
              </AppText>
              {show.date ? <AppText style={styles.date}>{formatDate(show.date)}</AppText> : null}
              {show.description ? <AppText style={styles.date}>{show.description}</AppText> : null}

              <View style={styles.actions}>
                <Button title="Share" icon="share-social" variant="ghost" small onPress={() => shareLink({ title: show.title, url: show.share_url || show.watch_url })} />
              </View>
            </View>

            {show.related?.length ? (
              <View style={styles.related}>
                <SectionHeader title="More to watch" actionLabel="" light style={{ paddingHorizontal: 0 }} />
                <FlatList
                  horizontal
                  data={show.related}
                  keyExtractor={(s) => String(s.id)}
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={{ gap: 12 }}
                  renderItem={({ item }) => <ShowCard show={item} variant="tile" />}
                />
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.ink },
  info: { padding: SPACING.md, gap: 6 },
  title: { color: COLORS.white, fontSize: 20, lineHeight: 27 },
  date: { color: 'rgba(255,255,255,0.72)' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: SPACING.sm + 2 },
  related: { marginTop: SPACING.lg, paddingHorizontal: SPACING.md },
});
