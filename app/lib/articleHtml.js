// The stylesheet that makes an article's HTML (from the website's editor) look right on a phone.
// Shared by ArticleBody.jsx (inside a WebView) and ArticleBody.web.jsx (inside the page).

export function articleCss(font = 'inter', scope = '') {
  const lora = font === 'lora';
  const s = scope ? `${scope} ` : '';
  const family = lora ? "Georgia, 'Times New Roman', serif" : "-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

  return `
  ${scope || 'body'} { font-family: ${family}; font-size: ${lora ? 18 : 17}px; line-height: ${lora ? 1.8 : 1.7}; color: #1f2937; word-wrap: break-word; overflow-wrap: anywhere; }
  ${s}p { margin: 0 0 16px; }
  ${s}h1, ${s}h2, ${s}h3, ${s}h4 { color: #111827; line-height: 1.3; margin: 26px 0 10px; font-weight: 800; }
  ${s}h1 { font-size: 26px; } ${s}h2 { font-size: 22px; } ${s}h3 { font-size: 19px; } ${s}h4 { font-size: 17px; }
  ${s}a { color: #800000; text-decoration: underline; }
  ${s}img { max-width: 100%; height: auto; display: block; border-radius: 10px; margin: 14px 0; }
  ${s}figure { margin: 14px 0; } ${s}figcaption { font-size: 13px; color: #6b7280; text-align: center; }
  ${s}blockquote { margin: 20px 0; padding: 12px 16px; border-left: 4px solid #C9A84C; background: #fbf6e6; color: #4b5563; font-style: italic; border-radius: 0 8px 8px 0; }
  ${s}ul, ${s}ol { padding-left: 22px; margin: 0 0 16px; } ${s}li { margin-bottom: 6px; }
  ${s}pre { overflow-x: auto; background: #0b1220; color: #f3f4f6; padding: 14px; border-radius: 8px; font-size: 14px; }
  ${s}code { font-size: 0.92em; }
  ${s}table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 15px; } ${s}td, ${s}th { border: 1px solid #e5e7eb; padding: 6px 8px; }
  ${s}.ql-ui { display: none; }
  ${scope ? `${scope} > div > :first-child` : '#c > :first-child'} { margin-top: 0; }
  `;
}
