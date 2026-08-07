<?php
require_once dirname(dirname(__DIR__)) . '/Views/TemplateRenderer.php';
require_once dirname(dirname(__DIR__)) . '/Seo/SeoMeta.php';

/** Rend les pages marketing publiques (investissement locatif, marchand de biens, analyse de rentabilité, guides). */
class PublicPageController
{
    private $renderer;

    public function __construct(TemplateRenderer $renderer = null)
    {
        $this->renderer = $renderer ?: new TemplateRenderer();
    }

    public function investissementLocatif()
    {
        $this->renderPage('public/investissement-locatif', array(
            'title' => 'Investissement locatif : méthode et outils pour investisseurs actifs',
            'description' => "Centralisez vos annonces, comparez-les aux ventes DVF et mesurez rendement, cash-flow et risques avant d'investir dans le locatif.",
            'path' => '/investissement-locatif'
        ));
    }

    public function marchandDeBiens()
    {
        $this->renderPage('public/marchand-de-biens', array(
            'title' => "Marchand de biens : qualifier une opération et sa marge",
            'description' => "Évaluez faisabilité, complexité, marge nette et prix maximum d'acquisition pour vos opérations de marchand de biens.",
            'path' => '/marchand-de-biens'
        ));
    }

    public function analyseRentabilite()
    {
        $this->renderPage('public/analyse-rentabilite', array(
            'title' => 'Analyse de rentabilité immobilière',
            'description' => 'Comprenez comment BienAuFait calcule rendement brut, rendement net, cash-flow et score de décision à partir de chaque annonce.',
            'path' => '/analyse-rentabilite'
        ));
    }

    public function guides()
    {
        $this->renderPage('public/guides', array(
            'title' => 'Guides opérationnels investissement locatif et marchand de biens',
            'description' => 'Guides pratiques et gratuits pour structurer votre stratégie : financement, villes cibles, fiscalité et simulateurs interactifs.',
            'path' => '/guides'
        ));
    }

    private function renderPage($template, array $seoOptions, array $context = array())
    {
        $context['seo'] = SeoMeta::build($seoOptions);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout($template, 'layouts/public', $context);
    }
}
