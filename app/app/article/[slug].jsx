// Story screen: cover photo, headline, byline, the article body, tags, sharing, comments and more stories to read.
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import AppText from '../../components/AppText';
import ArticleBody from '../../components/ArticleBody';
import ArticleCard from '../../components/ArticleCard';
import Button from '../../components/Button';
import CommentSection from '../../components/CommentSection';
import ScreenHeader from '../../components/ScreenHeader';
import SectionHeader from '../../components/SectionHeader';
import StateView from '../../components/StateView';
import { COLORS, CATEGORY_FALLBACK, RADIUS, SPACING } from '../../constants/theme';
import { compactNumber, formatDate } from '../../lib/format';
import { shareLink } from '../../lib/links';
import { useFetch } from '../../lib/useFetch';

export default function ArticleScreen() {
  const { slug } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/articles/${slug}`);
  const article = data?.data;
  const [posted, setPosted] = useState([]); // comments added from this screen, shown straight away
  const accent = article?.category?.color || CATEGORY_FALLBACK;

  const share = () => article && shareLink({ title: article.title, url: article.web_url });
  const comments = article ? [...article.comments, ...posted] : [];

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title={article?.category?.name ?? 'Story'}
        right={
          article ? (
            <Pressable accessibilityRole="button" accessibilityLabel="Share this story" onPress={share} hitSlop={8} style={styles.shareIcon}>
              <Ionicons name="share-social-outline" size={22} color={COLORS.black} />
            </Pressable>
          ) : null
        }
      />

      <StateView loading={loading} error={error} onRetry={retry}>
        {article ? (
          <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}>
              <Image source={{ uri: article.image }} contentFit="cover" transition={250} style={styles.cover} />
              {article.image_caption ? (
                <AppText variant="caption" style={styles.caption}>
                  {article.image_caption}
                </AppText>
              ) : null}

              <View style={styles.pad}>
                {article.category ? (
                  <AppText variant="overline" style={{ color: accent, marginTop: SPACING.md }}>
                    {article.category.name}
                  </AppText>
                ) : null}
                <AppText variant="title" style={styles.title}>
                  {article.title}
                </AppText>
                <View style={styles.byline}>
                  <View style={[styles.avatar, { backgroundColor: accent }]}>
                    <AppText style={styles.avatarText}>{(article.author || 'O')[0].toUpperCase()}</AppText>
                  </View>
                  <View style={{ flex: 1 }}>
                    <AppText variant="label">{article.author}</AppText>
                    <AppText variant="caption">
                      {formatDate(article.published_at)} · {article.reading_time} min read{article.show_views ? ` · ${compactNumber(article.views)} views` : ''}
                    </AppText>
                  </View>
                </View>

                <View style={styles.body}>
                  <ArticleBody html={article.content_html} font={article.body_font} />
                </View>

                {article.tags?.length ? (
                  <View style={styles.tags}>
                    {article.tags.map((t) => (
                      <View key={t.slug} style={styles.tag}>
                        <AppText variant="caption" style={{ color: COLORS.gray900 }}>
                          #{t.name}
                        </AppText>
                      </View>
                    ))}
                  </View>
                ) : null}

                <Button title="Share this story" icon="share-social" variant="outline" onPress={share} style={{ marginTop: SPACING.lg }} />

                <View style={styles.comments}>
                  <CommentSection slug={article.slug} comments={comments} onPosted={(c) => setPosted((p) => [...p, c])} />
                </View>
              </View>

              {article.related?.length ? (
                <View style={{ marginTop: SPACING.lg }}>
                  <SectionHeader title="More stories" actionLabel="News" onAction={() => router.push('/news')} />
                  <View style={styles.related}>
                    {article.related.map((a) => (
                      <View key={a.id} style={styles.relatedItem}>
                        <ArticleCard article={a} variant="row" />
                      </View>
                    ))}
                  </View>
                </View>
              ) : null}
            </ScrollView>
          </KeyboardAvoidingView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.white },
  shareIcon: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
  content: { paddingBottom: SPACING.xxl },
  cover: { width: '100%', aspectRatio: 16 / 9, backgroundColor: COLORS.ink },
  caption: { paddingHorizontal: SPACING.md, paddingVertical: 8, backgroundColor: COLORS.gray100, fontStyle: 'italic' },
  pad: { paddingHorizontal: SPACING.md },
  title: { marginTop: 6, fontSize: 24, lineHeight: 31 },
  byline: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: SPACING.md, paddingBottom: SPACING.md, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.border },
  avatar: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: COLORS.white, fontWeight: '800', fontSize: 15 },
  body: { marginTop: SPACING.md },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: SPACING.md },
  tag: { paddingHorizontal: 12, paddingVertical: 5, borderRadius: RADIUS.full, backgroundColor: COLORS.gray100 },
  comments: { marginTop: SPACING.xl, paddingTop: SPACING.lg, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
  related: { paddingHorizontal: SPACING.md },
  relatedItem: { paddingVertical: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.border },
});
