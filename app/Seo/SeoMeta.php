<?php
/** Construit les métadonnées SEO (title, description, canonical, Open Graph, JSON-LD) pour les pages publiques. */
class SeoMeta
{
    const BRAND = 'BienAuFait';
    const DEFAULT_OG_IMAGE = '/assets/img/screenshots/galerie.png';

    public static function baseUrl()
    {
        $env = getenv('SIGMAIMMO_BASE_URL');
        if (is_string($env) && trim($env) !== '') {
            return rtrim(trim($env), '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        return $scheme . '://' . $host;
    }

    public static function absoluteUrl($path)
    {
        if (preg_match('#^https?://#i', (string)$path)) {
            return $path;
        }
        return self::baseUrl() . '/' . ltrim((string)$path, '/');
    }

    /**
     * $options : title, description, path (URL relative canonique), type ('website'|'article'),
     * image (URL relative ou absolue), structured_data (tableau PHP encodé en JSON-LD).
     */
    public static function build(array $options)
    {
        $title = isset($options['title']) ? trim((string)$options['title']) : self::BRAND;
        $pageTitle = $title !== '' ? $title . ' — ' . self::BRAND : self::BRAND;
        $description = isset($options['description']) ? trim((string)$options['description']) : '';
        $path = isset($options['path']) ? (string)$options['path'] : '/';
        $canonical = self::absoluteUrl($path);
        $type = isset($options['type']) ? (string)$options['type'] : 'website';
        $image = self::absoluteUrl(isset($options['image']) && $options['image'] !== '' ? $options['image'] : self::DEFAULT_OG_IMAGE);

        $structuredDataJson = '';
        if (!empty($options['structured_data']) && is_array($options['structured_data'])) {
            $structuredDataJson = json_encode($options['structured_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return array(
            'title' => $pageTitle,
            'meta_description' => $description,
            'canonical' => $canonical,
            'og_type' => $type,
            'og_title' => $title !== '' ? $title : self::BRAND,
            'og_description' => $description,
            'og_image' => $image,
            'structured_data_json' => $structuredDataJson
        );
    }
}
