<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer;


/**
 * Class to provide admin and user-facing menus.
 * @package mailer
 */
class Menu
{
    /**
     * Create the user menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function User($view='')
    {
        global $LANG_MLR;

        USES_lib_admin();
        $menu_arr = array(
            array(
                'url'  => Config::get('url') . '/index.php',
                'text' => $LANG_MLR['campaigns'],
                'active' => $view == 'list' ? true : false,
            ),
        );
        if (plugin_ismoderator_mailer()) {
            $menu_arr[] = array(
                'url' => Config::get('admin_url') . '/index.php',
                'text' => $LANG_MLR['admin'],
            );
        }
        return \ADMIN_createMenu($menu_arr, '');
    }


    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF, $LANG_MLR;
        USES_lib_admin();
        $API = API::getInstance();
        $features = $API->getFeatures();

        $retval = '';
        $admin_url = Config::getInstance()->get('admin_url');
        $menu_arr = array();
        if (in_array('campaigns', $features)) {
            $menu_arr[] = array(
                'url'   => $admin_url . '/index.php?campaigns',
                'text'  => $LANG_MLR['campaigns'],
                'active' => $view == 'campaigns' ? true : false,
            );
        }
        if (in_array('queue', $features)) {
            $menu_arr[] = array(
                'url'  => $admin_url . '/index.php?queue',
                'text' => $LANG_MLR['queue'],
                'active' => $view == 'queue' ? true : false,
            );
        }
        if (in_array('subscribers', $features)) {
            $menu_arr[] = array(
                'url'   => $admin_url . '/index.php?subscribers',
                'text'  => $LANG_MLR['subscribers'],
                'active' => $view == 'subscribers' ? true : false,
            );
        }
        $menu_arr[] = array(
            'url' => $admin_url . '/index.php?maintenance',
            'text' => $LANG_MLR['maintenance'],
            'active' => $view == 'maintenance' ? true : false,
        );
        $menu_arr[] = array(
            'url'   => $_CONF['site_admin_url'],
            'text'  => _('Admin Home'),
            'active' => false,
        );

        $admin_hdr = 'admin_item_hdr';
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('title', 'mailer_title.thtml');
        $T->set_var(array(
            'title' => _('Mailer Administration') . ' v' . Config::get('pi_version'),
        ) );
        $hlp_text = _('Mail Handler') . ': ' . $API->getName();
        if (!$API->isConfigured()) {
            $hlp_text .= '<br />* Please configure the API key for ' . $API->getName();
        }
        $hlp_opts = $API->getMenuHelp();
        if (!empty($hlp_opts)) {
            $hlp_text .= $hlp_opts;
        }
        $retval .= $T->parse('', 'title');
        $retval .= ADMIN_createMenu($menu_arr, $hlp_text, plugin_geticon_mailer());
        return $retval;
    }


    /**
     * Create the administrator sub-menu for the Catalog option.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminSubscribers($view='')
    {
        global $LANG_MLR;

        if ($view == '') $view = 'subscribers';
        $admin_url = Config::get('admin_url');
        $menu_arr = array(
            array(
                'url' => $admin_url . '/index.php?subscribers',
                'text' => $LANG_MLR['list'],
                'active' => $view == 'subscribers' ? true : false,
            ),
            array(
                'url' => $admin_url . '/import.php?import',
                'text' => $LANG_MLR['import'],
                'active' => $view == 'do_import' ? true : false,
            ),
            array(
                'url' => $admin_url . '/import.php?export=x',
                'text' => $LANG_MLR['export'],
                'active' => $view == 'export' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create the administrator sub-menu for the Shipping option.
     * Includes shipper setup and shipment listing.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminCampaigns($view='')
    {
        global $LANG_MLR, $LANG_ADMIN, $_CONF;

        if ($view == '') $view = 'campaigns';
        $menu_arr = array (
            array(
                'url' => Config::get('admin_url') . '/index.php?campaigns',
                'text' => $LANG_MLR['list'],
                'active' => $view == 'campaigns' ? true : false,
            ),
            array(
                'url' => Config::get('admin_url') . '/index.php?edit=x',
                'text' => $LANG_ADMIN['create_new'],
                'active' => $view == 'create' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create the administrator sub-menu for the Orders option.
     * Includes orders and shipment listing.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminQueue($view='')
    {
        global $LANG_MLR;

        $admin_url = Config::get('admin_url');
        $menu_arr = array(
            array(
                'url' => $admin_url . '/index.php?queue',
                'text' => $LANG_MLR['list'],
                'active' => $view == 'queue' ? true : false,
            ),
            array(
                'url' => $admin_url . '/index.php?purgequeue=x',
                'text' => $LANG_MLR['purge_queue'],
                'active' => $view == 'purgequeue' ? true : false,
            ),
            array(
                'url' => $admin_url . '/index.php?resetqueue=x',
                'text' => $LANG_MLR['reset_queue'],
                'active' => $view == 'resetqueue' ? true : false,
            ),
            array(
                'url' => $admin_url . '/index.php?flushqueue=x',
                'text' => $LANG_MLR['flush_queue'],
                'active' => $view == 'flushqueue' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create a submenu using a standard template.
     *
     * @param   array   $menu_arr   Array of menu items
     * @return  string      HTML for the submenu
     */
    private static function _makeSubMenu($menu_arr)
    {
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('menu', 'submenu.thtml');
        $T->set_block('menu', 'menuItems', 'items');
        foreach ($menu_arr as $mnu) {
            $url = COM_createLink($mnu['text'], $mnu['url']);
            $T->set_var(array(
                'active'    => $mnu['active'],
                'url'       => $url,
            ) );
            $T->parse('items', 'menuItems', true);
        }
        $T->parse('output', 'menu');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Display only the page title.
     * Used for pages that do not feature a menu, such as the catalog.
     *
     * @param   string  $page_title     Page title text
     * @param   string  $page           Page name being displayed
     * @return  string      HTML for page title section
     */
    public static function pageTitle($page_title = '', $page='')
    {
        global $_USER;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('title', 'mailer_title.thtml');
        $T->set_var(array(
            'title' => $page_title,
            'is_admin' => plugin_ismoderator_mailer(),
            'link_admin' => plugin_ismoderator_mailer(),
        ) );
        return $T->parse('', 'title');
    }


    /**
     * Display the site header, with or without blocks according to configuration.
     *
     * @param   string  $title  Title to put in header
     * @param   string  $meta   Optional header code
     * @return  string          HTML for site header, from COM_siteHeader()
     */
    public static function siteHeader($title='', $meta='')
    {
        $retval = '';
        switch(Config::get('displayblocks')) {
        case 2:     // right only
        case 0:     // none
            $retval .= COM_siteHeader('none', $title, $meta);
            break;

        case 1:     // left only
        case 3:     // both
        default :
            $retval .= COM_siteHeader('menu', $title, $meta);
            break;
        }
        return $retval;
    }


    /**
     * Display the site footer, with or without blocks as configured.
     *
     * @return  string      HTML for site footer, from COM_siteFooter()
     */
    public static function siteFooter()
    {
        $retval = '';

        switch(Config::get('displayblocks')) {
        case 2 : // right only
        case 3 : // left and right
            $retval .= COM_siteFooter();
            break;

        case 0: // none
        case 1: // left only
        default :
            $retval .= COM_siteFooter();
            break;
        }
        return $retval;
    }

}

?>


