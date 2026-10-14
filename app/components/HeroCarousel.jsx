// The home screen's featured-stories carousel: swipe sideways (it also advances by itself every few seconds
// until you touch it), with dots underneath.
import { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, StyleSheet, View, useWindowDimensions } from 'react-native';
import { COLORS, SPACING } from '../constants/theme';
import ArticleCard from './ArticleCard';

const GAP = 12;

export default function HeroCarousel({ articles }) {
  const { width } = useWindowDimensions();
  const cardWidth = Math.min(width, 720) - SPACING.md * 2;
  const step = cardWidth + GAP;
  const list = useRef(null);
  const [index, setIndex] = useState(0);
  const touched = useRef(false);

  const goTo = useCallback(
    (next) => {
      list.current?.scrollToOffset({ offset: next * step, animated: true });
      setIndex(next);
    },
    [step],
  );

  useEffect(() => {
    if (articles.length < 2) return undefined;
    const timer = setInterval(() => {
      if (touched.current) return;
      goTo((index + 1) % articles.length);
    }, 6000);
    return () => clearInterval(timer);
  }, [articles.length, index, goTo]);

  return (
    <View>
      <FlatList
        ref={list}
        data={articles}
        horizontal
        keyExtractor={(item) => String(item.id)}
        showsHorizontalScrollIndicator={false}
        snapToInterval={step}
        decelerationRate="fast"
        contentContainerStyle={{ paddingHorizontal: SPACING.md, gap: GAP }}
        onScrollBeginDrag={() => {
          touched.current = true;
        }}
        onMomentumScrollEnd={(e) => setIndex(Math.max(0, Math.min(articles.length - 1, Math.round(e.nativeEvent.contentOffset.x / step))))}
        renderItem={({ item }) => <ArticleCard article={item} variant="hero" style={{ width: cardWidth }} />}
      />
      {articles.length > 1 ? (
        <View style={styles.dots}>
          {articles.map((a, i) => (
            <View key={a.id} style={[styles.dot, i === index && styles.dotActive]} />
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 6, marginTop: 12 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: COLORS.gray300 },
  dotActive: { width: 22, backgroundColor: COLORS.primary },
});
