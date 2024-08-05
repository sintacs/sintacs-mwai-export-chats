<?php

/**
 * Plugin Name: Sintacs Mwai Export Chats
 * Plugin URI: https://store.sintacs.de
 * Description: Export AI Engine (Pro) chatbot conversations in various formats.
 * Version: 2.0.0
 * Author: Dirk Krölls
 * Author URI: https://sintacs.de
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sintacs-mwai-export-chats
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */
namespace SintacsMwaiExportChats;

use ChatExporter;
if ( !function_exists( 'sintacs_mwai_export' ) ) {
    // Create a helper function for easy SDK access.
    function sintacs_mwai_export() {
        global $sintacs_mwai_export;
        if ( !isset( $sintacs_mwai_export ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $sintacs_mwai_export = fs_dynamic_init( array(
                'id'             => '16079',
                'slug'           => 'sintacs-mwai-export-chats',
                'type'           => 'plugin',
                'public_key'     => 'pk_1fc14fb53e718394d566279e3e031',
                'is_premium'     => false,
                'premium_suffix' => 'Premium',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'menu'           => array(
                    'slug'    => 'chats_export',
                    'contact' => false,
                    'support' => false,
                    'parent'  => array(
                        'slug' => 'meowapps-main-menu',
                    ),
                ),
                'is_live'        => true,
            ) );
        }
        return $sintacs_mwai_export;
    }

    // Init Freemius.
    sintacs_mwai_export();
    // Signal that SDK was initiated.
    do_action( 'sintacs_mwai_export_loaded' );
}
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Include WordPress plugin functions
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once 'includes/class-chat-exporter.php';
require_once 'includes/class-admin-page.php';
require_once 'includes/class-database.php';
class SintacsMwaiExportChats {
    private ChatExporter $chatExporter;

    private AdminPage $adminPage;

    private Database $database;

    public function __construct() {
        $this->database = new Database();
        $this->chatExporter = new ChatExporter($this->database);
        $this->adminPage = new AdminPage($this->chatExporter, $this->database);
        add_action( 'plugins_loaded', [$this, 'loadTextdomain'] );
        add_action( 'admin_menu', [$this, 'addAdminMenu'], 11 );
        add_filter( 'query_vars', [$this, 'addQueryVars'] );
    }

    public function loadTextdomain() : void {
        load_plugin_textdomain( 'sintacs-mwai-export-chats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function addAdminMenu() : void {
        if ( !$this->database->isAiEngineActive() ) {
            add_menu_page(
                __( 'AI Engine Export', 'sintacs-mwai-export-chats' ),
                __( 'AI Engine Export', 'sintacs-mwai-export-chats' ),
                'manage_options',
                'chats_export',
                [$this->adminPage, 'render'],
                'dashicons-admin-comments',
                100
            );
        } else {
            add_submenu_page(
                'meowapps-main-menu',
                __( 'AI Engine Export', 'sintacs-mwai-export-chats' ),
                __( 'AI Engine Export', 'sintacs-mwai-export-chats' ),
                'manage_options',
                'chats_export',
                [$this->adminPage, 'render']
            );
        }
    }

    public function addQueryVars( array $vars ) : array {
        $customVars = [
            'per-page',
            'paged',
            'export-format',
            'chat-ids',
            'action'
        ];
        return array_merge( $vars, $customVars );
    }

}

new SintacsMwaiExportChats();
function handle_export_chats() {
    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'sintacs-mwai-export-chats' ) );
    }
    if ( !isset( $_POST['sintacs_mwai_nonce'] ) || !wp_verify_nonce( $_POST['sintacs_mwai_nonce'], 'sintacs_mwai_export_chats' ) ) {
        wp_die( __( 'Security check failed. Please try again.', 'sintacs-mwai-export-chats' ) );
    }
    $database = new Database();
    $filters = [
        'date-from-filter' => $_POST['date-from-filter'] ?? '',
        'date-to-filter'   => $_POST['date-to-filter'] ?? '',
        'userID-filter'    => $_POST['userID-filter'] ?? '',
        'botID-filter'     => $_POST['botID-filter'] ?? '',
        'chatID-filter'    => $_POST['chatID-filter'] ?? '',
    ];
    $per_page = ( isset( $_POST['per-page'] ) ? intval( $_POST['per-page'] ) : 20 );
    // Handle refresh action (per-page change)
    if ( isset( $_POST['refresh'] ) ) {
        $per_page = ( isset( $_POST['per-page'] ) ? intval( $_POST['per-page'] ) : 20 );
        $url = add_query_arg( array_merge( [
            'per-page' => $per_page,
        ], $filters ), admin_url( 'admin.php?page=chats_export' ) );
        wp_redirect( $url );
        exit;
    }
    // Handle export action
    if ( isset( $_POST['export_format'] ) && $_POST['filter'] !== '1' ) {
        $export_format = $_POST['export_format'];
        $export_all = isset( $_POST['export_all'] ) && $_POST['export_all'] == '1';
        $chats = $database->fetchChatsWithPagination( PHP_INT_MAX, 0, $filters );
        if ( empty( $chats ) ) {
            wp_die( __( 'No chats found for the given filters.', 'sintacs-mwai-export-chats' ) );
        }
        $chat_ids = ( $export_all ? array_column( $chats, 'id' ) : $_POST['chat_ids'] ?? [] );
        $chatExporter = new ChatExporter($database);
        $chatExporter->export( $export_format, $chat_ids );
        exit;
    }
    if ( $_POST['filter'] == '1' ) {
        $url = add_query_arg( array_merge( [
            'per-page' => $per_page,
        ], $filters ), admin_url( 'admin.php?page=chats_export' ) );
        wp_redirect( $url );
        exit;
    }
    // If no recognized action was taken, redirect back to the admin page
    wp_redirect( admin_url( 'admin.php?page=chats_export' ) );
    exit;
}

add_action( 'admin_post_sintacs_mwai_export_chats', 'SintacsMwaiExportChats\\handle_export_chats' );
function sintacs_mwai_export_chats_load_textdomain() {
    load_plugin_textdomain( 'sintacs-mwai-export-chats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'SintacsMwaiExportChats\\sintacs_mwai_export_chats_load_textdomain' );
function sintacs_mwai_export_chats_check_dependencies() {
    if ( !function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $ai_engine_active = is_plugin_active( 'ai-engine/ai-engine.php' ) || is_plugin_active( 'ai-engine-pro/ai-engine-pro.php' );
    if ( !$ai_engine_active ) {
        add_action( 'admin_notices', 'SintacsMwaiExportChats\\sintacs_mwai_export_chats_dependency_notice' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'mwai_chats';
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) == $table_name;
    if ( !$table_exists ) {
        add_action( 'admin_notices', 'SintacsMwaiExportChats\\sintacs_mwai_export_chats_table_notice' );
    }
}

add_action( 'admin_init', 'SintacsMwaiExportChats\\sintacs_mwai_export_chats_check_dependencies' );
function sintacs_mwai_export_chats_dependency_notice() {
    echo '<div class="error"><p>' . __( 'Sintacs Mwai Export Chats requires the AI Engine plugin (free or pro version) to be installed and activated.', 'sintacs-mwai-export-chats' ) . '</p></div>';
}

function sintacs_mwai_export_chats_table_notice() {
    echo '<div class="error"><p>' . __( 'The AI Engine chat table does not exist. Please enable discussions in the AI Engine settings -> Chatbot tab.', 'sintacs-mwai-export-chats' ) . '</p></div>';
}
