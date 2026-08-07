CREATE TABLE IF NOT EXISTS blog_articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    meta_description TEXT NOT NULL DEFAULT '',
    canonical_url TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    excerpt TEXT NOT NULL DEFAULT '',
    content_json TEXT NOT NULL DEFAULT '[]',
    cover_image_url TEXT NOT NULL DEFAULT '',
    author TEXT NOT NULL DEFAULT '',
    generated_by_llm INTEGER NOT NULL DEFAULT 0,
    published_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_blog_articles_slug ON blog_articles(slug);
CREATE INDEX IF NOT EXISTS idx_blog_articles_status_published_at ON blog_articles(status, published_at);
