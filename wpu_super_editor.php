<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Super Editor
Plugin URI: https://github.com/WordPressUtilities/wpu_super_editor
Update URI: https://github.com/WordPressUtilities/wpu_super_editor
Description: A WordPress Editor role which can handle users
Version: 0.4.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_super_editor
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
Domain Path: /lang
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

/* ----------------------------------------------------------
  Add Super Editor role
---------------------------------------------------------- */

add_action('init', function () {

    if (!get_site_url()) {
        return;
    }

    /* Details */
    $role_opt = 'wpu_super_editor_role';
    $role_id = 'super_editor';
    $role_name = 'Super Editor';

    /* Start on editor role */
    $editor_role = get_role('editor');
    $role_details = $editor_role->capabilities;

    /* Add new capacities */
    $role_details['create_users'] = true;
    $role_details['add_users'] = true;
    $role_details['list_users'] = true;
    $role_details['edit_users'] = true;
    $role_details['remove_users'] = true;
    $role_details['delete_users'] = true;
    $role_details['promote_users'] = true;

    /* Network capabilities */
    if (is_multisite()) {
        $role_details['manage_network_users'] = true;
    }

    /* WooCommerce */
    $role_details['edit_product'] = true;
    $role_details['read_product'] = true;
    $role_details['delete_product'] = true;
    $role_details['edit_products'] = true;
    $role_details['edit_others_products'] = true;
    $role_details['publish_products'] = true;
    $role_details['read_private_products'] = true;
    $role_details['delete_products'] = true;
    $role_details['delete_private_products'] = true;
    $role_details['delete_published_products'] = true;
    $role_details['delete_others_products'] = true;
    $role_details['edit_private_products'] = true;
    $role_details['edit_published_products'] = true;
    $role_details['manage_product_terms'] = true;
    $role_details['edit_product_terms'] = true;
    $role_details['delete_product_terms'] = true;
    $role_details['assign_product_terms'] = true;

    /* Additional roles */
    $role_details = apply_filters('wpu_super_editor__roles', $role_details);

    /* Yoast SEO */
    $role_details['wpseo_bulk_edit'] = true;
    $role_details['wpseo_edit_advanced_metadata'] = true;
    $role_details['wpseo_manage_options'] = true;

    $role_version = md5($role_id . $role_name . json_encode($role_details));

    /* Update role only if it doesn’t exists */
    if (get_option($role_opt) != $role_version) {
        if (get_role($role_id)) {
            remove_role($role_id);
        }
        add_role($role_id, $role_name, $role_details);
        update_option($role_opt, $role_version);
    }
});

/* ----------------------------------------------------------
  Clean admin menus
---------------------------------------------------------- */

add_action('admin_menu', function () {
    global $submenu;
    /* For non admins only */
    if (current_user_can('activate_plugins')) {
        return;
    }

    /* Remove menus */
    remove_menu_page('tools.php');
    remove_submenu_page('themes.php', 'themes.php');
    remove_menu_page('options-general.php');

    /* Custom plugins */
    remove_menu_page('edit.php?post_type=acf-field-group');

    /* Remove some theme parts */
    if (isset($submenu['themes.php'])) {
        foreach ($submenu['themes.php'] as $i => $item) {
            if (isset($item[1], $item[4]) && $item[1] == 'edit_theme_options' && $item[4] == 'hide-if-no-customize') {
                unset($submenu['themes.php'][$i]);
            }
        }
    }
});

/* Prevent access to options-general.php
-------------------------- */

add_action('admin_init', function () {
    if (current_user_can('activate_plugins')) {
        return;
    }
    if (!isset($_SERVER['REQUEST_URI'])) {
        return;
    }
    if (strpos($_SERVER['REQUEST_URI'], 'options-general.php') !== false) {
        wp_redirect(admin_url('index.php'));
    }
});

/* ----------------------------------------------------------
  Load translation
---------------------------------------------------------- */

add_action('after_setup_theme', function () {
    $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
    if (strpos(__DIR__, 'mu-plugins') !== false) {
        load_muplugin_textdomain('wpu_super_editor', $lang_dir);
    } else {
        load_plugin_textdomain('wpu_super_editor', false, $lang_dir);
    }
    $plugin_description = __('A WordPress Editor role which can handle users', 'wpu_super_editor');
});

/* ----------------------------------------------------------
  Only an admin can add a new admin
---------------------------------------------------------- */

add_filter('editable_roles', function ($roles) {
    if (isset($roles['administrator']) && !current_user_can('activate_plugins')) {
        unset($roles['administrator']);
    }
    return $roles;
}, 10, 1);

/* ----------------------------------------------------------
  Security
---------------------------------------------------------- */

/* Avoid edition of admin users by non admins */
add_action('current_screen', function () {

    /* Only once */
    if (defined('WPU_SUPER_EDITOR_CHECK_USER_ADMIN_EDITION')) {
        return;
    }
    define('WPU_SUPER_EDITOR_CHECK_USER_ADMIN_EDITION', 1);

    /* Only logged-in non admin users */
    if (!is_admin() || !is_user_logged_in() || current_user_can('administrator')) {
        return;
    }

    /* Only on user edit */
    $screen = get_current_screen();
    if (!isset($screen->base)) {
        return;
    }

    $user_id = false;

    if ($screen->base == 'user-edit' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        $user_id = $_GET['user_id'];
    }

    if ($screen->base == 'users' && isset($_GET['action'], $_GET['user']) && is_numeric($_GET['user']) && $_GET['action'] == 'delete') {
        $user_id = $_GET['user'];
    }

    if ($screen->base == 'users' && isset($_GET['action'], $_GET['users']) && ($_GET['action'] == 'delete' || $_GET['action'] == 'resetpassword')) {
        foreach ($_GET['users'] as $user_id) {
            if (wpu_super_editor_is_user_admin($user_id)) {
                wp_redirect(admin_url('users.php'));
                die;
            }
        }
    }

    if (!$user_id) {
        return;
    }

    /* Only on administrator pages */
    if (!isset($user_id) || !is_numeric($user_id)) {
        return;
    }

    /* If user is an administrator, prevent edition */
    if (wpu_super_editor_is_user_admin($user_id)) {
        wp_redirect(admin_url('users.php'));
        die;
    }
});

/**
 * Check if an user is an administrator
 * @param  int $user_id
 * @return boolean
 */
function wpu_super_editor_is_user_admin($user_id) {
    if (!is_numeric($user_id) && !is_int($user_id)) {
        return false;
    }
    /* Get user details */
    $user_page = get_user_by('ID', $user_id);
    if (!$user_page) {
        return false;
    }

    return in_array('administrator', $user_page->roles, true);
}

/* Prevent access to invalid actions
-------------------------- */

add_filter('user_row_actions', function ($actions, $user) {
    if (current_user_can('administrator')) {
        return $actions;
    }

    if (wpu_super_editor_is_user_admin($user->ID)) {
        if (!isset($actions['view'])) {
            $actions = array();
        } else {
            $actions = array('view' => $actions['view']);
        }
        return $actions;
    }

    return $actions;
}, 99, 2);

/* Prevent user deletion
-------------------------- */

add_action('delete_user', function ($user_id) {
    if (!wpu_super_editor_is_user_admin($user_id) || current_user_can('administrator')) {
        return;
    }
    wp_die("User is an administrator and cant be deleted by a non-administrator.");
});

/* ----------------------------------------------------------
  Plugins
---------------------------------------------------------- */

/* Happy files
-------------------------- */

add_filter('option_happyfiles_folder_access', function ($values) {
    if (is_array($values)) {
        $values['super_editor'] = 'full';
    }
    return $values;
}, 10, 1);

/* ----------------------------------------------------------
  Allow more user roles to edit private policy page
---------------------------------------------------------- */

/* Thx to https://wordpress.stackexchange.com/a/325784 */

add_action('map_meta_cap', function ($caps, $cap, $user_id, $args) {

    if ($cap != 'manage_privacy_options' || !$user_id || !is_user_logged_in()) {
        return $caps;
    }

    if (current_user_can('delete_users')) {
        $manage_name = is_multisite() ? 'manage_network' : 'manage_options';
        $caps = array_diff($caps, [$manage_name]);
    }
    return $caps;
}, 1, 4);

/* ----------------------------------------------------------
  Disable CSS personalization for non-admins
---------------------------------------------------------- */

add_action('customize_register', function ($wp_customize) {
    if (current_user_can('administrator')) {
        return;
    }
    $wp_customize->remove_section('custom_css');
}, 99);
