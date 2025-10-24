<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.alamartehelixtxt
 * @version     2.1.1
 * @author      ALAMARTE Ingeniería
 * @license     GNU/GPLv3+
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class PlgSystemAlamartehelixtxt extends CMSPlugin
{
    protected $app;

    public function onAfterDispatch()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        $option = $input->getCmd('option', '');
        $helixParam = $input->getCmd('helix', '');

        $isTemplateManager = ($option === 'com_templates');
        $isHelixAjax = ($option === 'com_ajax' && strtolower($helixParam) === 'ultimate');

        try {
            $uri = Uri::getInstance();
            $path = (string) $uri->toString();
        } catch (\Throwable $e) {
            $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        }

        $containsHelix = strpos(strtolower($path), 'helix') !== false;

        if (!($isTemplateManager || $isHelixAjax || $containsHelix)) {
            return;
        }

        $doc = $app->getDocument();

        // 1) Intentar cargar JSON de traducciones para el idioma actual desde media/js/lang/{tag}.json
        $langTag = 'en-GB';
        try {
            $langTag = $app->getLanguage()->getTag() ?: 'en-GB';
        } catch (\Throwable $e) {
            $langTag = 'en-GB';
        }

        $jsonCandidates = [
            __DIR__ . '/media/js/lang/' . $langTag . '.json',
            __DIR__ . '/media/js/lang/en-GB.json',
            __DIR__ . '/media/js/lang/es-ES.json'
        ];

        foreach ($jsonCandidates as $jsonPath) {
            if (is_file($jsonPath) && is_readable($jsonPath)) {
                $json = file_get_contents($jsonPath);
                $decoded = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Inyectar objeto de traducciones en el scope global del admin
                    $doc->addScriptDeclaration('window.__alamarteLang = ' . $json . ';');
                    break;
                }
            }
        }

        // 2) Registrar script externo (para que esté disponible cuando Helix cargue modales por AJAX)
        $root = rtrim(Uri::root(true), '/');
        $scriptUrl = $root . '/plugins/system/alamartehelixtxt/media/js/admin-helix-inline.js';

        try {
            // Añade el script como archivo externo (no inline) para que el navegador lo cargue de forma persistente
            $doc->addScript($scriptUrl);
        } catch (\Throwable $e) {
            // Fallback: cargar el JS inline si addScript falla
            $mediaPath = __DIR__ . '/media/js/admin-helix-inline.js';
            if (is_file($mediaPath) && is_readable($mediaPath)) {
                $js = file_get_contents($mediaPath);
                if ($js !== false && trim($js) !== '') {
                    $doc->addScriptDeclaration($js);
                }
            }
        }
    }
}