<?php
require_once __DIR__ . '/SeoMeta.php';
require_once dirname(__DIR__) . '/Repositories/BlogArticleRepository.php';

/** Liste les URLs publiques indexables pour générer sitemap.xml. */
class Sitemap
{
    private $articles;

    public function __construct(BlogArticleRepository $articles = null)
    {
        $this->articles = $articles ?: new BlogArticleRepository();
    }

    public function entries()
    {
        $entries = array(
            array('path' => '/', 'changefreq' => 'weekly', 'priority' => '1.0'),
            array('path' => '/investissement-locatif', 'changefreq' => 'monthly', 'priority' => '0.8'),
            array('path' => '/marchand-de-biens', 'changefreq' => 'monthly', 'priority' => '0.8'),
            array('path' => '/analyse-rentabilite', 'changefreq' => 'monthly', 'priority' => '0.8'),
            array('path' => '/guides', 'changefreq' => 'monthly', 'priority' => '0.7'),
            array('path' => '/guide-investissement-locatif.html', 'changefreq' => 'monthly', 'priority' => '0.6'),
            array('path' => '/guide-mdb-division-parcellaire.html', 'changefreq' => 'monthly', 'priority' => '0.6'),
            array('path' => '/blog', 'changefreq' => 'daily', 'priority' => '0.7')
        );

        foreach ($this->articles->allPublished() as $article) {
            $entries[] = array(
                'path' => '/blog/' . $article['slug'],
                'lastmod' => substr((string)$article['updated_at'], 0, 10),
                'changefreq' => 'monthly',
                'priority' => '0.6'
            );
        }

        return $entries;
    }

    public function toXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->entries() as $entry) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars(SeoMeta::absoluteUrl($entry['path']), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            if (!empty($entry['changefreq'])) {
                $xml .= '    <changefreq>' . $entry['changefreq'] . '</changefreq>' . "\n";
            }
            if (!empty($entry['priority'])) {
                $xml .= '    <priority>' . $entry['priority'] . '</priority>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";
        return $xml;
    }
}
