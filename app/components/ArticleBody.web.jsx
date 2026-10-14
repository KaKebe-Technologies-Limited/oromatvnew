// Desktop-browser twin of ArticleBody.jsx: the article HTML is put straight into the page (it is already sanitised
// by the server). Only used when previewing the app with `npx expo start --web`.
import { createElement } from 'react';
import { articleCss } from '../lib/articleHtml';

export default function ArticleBody({ html, font = 'inter' }) {
  return createElement(
    'div',
    { className: 'oroma-article' },
    createElement('style', null, articleCss(font, '.oroma-article')),
    createElement('div', { dangerouslySetInnerHTML: { __html: html } }),
  );
}
