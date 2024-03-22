<?php
/**
 * Plugin Name: Sintacs Meow AI Engine Discussions Export
 * Description: Plugin to xport Discussions/Chats from Meow AI Engine
 * Version:     1.05
 * Author:      Dirk Krölls / Sintacs | chats-export@sintacs.de
 * Author URI:  https://sintacs.de
 * License:     GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace SintacsMwaiExportChats;

use MwaiTCPDF;

// php full error reporting
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

class SintacsMwaiExportChats
{
    var bool $ai_engine_is_active = false;

    public function __construct()
    {
        global $wpdb;

        add_action('admin_menu',[$this,'add_admin_menu'],11);
        //add_action('admin_enqueue_scripts','enqueue_datepicker_assets');
        add_filter('query_vars',array($this,'add_query_vars'));

        // Check if either 'ai-engine-pro/ai-engine-pro.php' or 'ai-engine/ai-engine.php' plugin is installed and active
        /*
        if (is_plugin_active('ai-engine-pro/ai-engine.php') || is_plugin_active('ai-engine/ai-engine.php')) {
            $this->ai_engine_is_active = true;
        }
        */

        // Check if the table with the chats exists
        // Get the table name with the correct prefix
        $table_name = $wpdb->prefix . 'mwai_chats';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $this->ai_engine_is_active = true;
        } else {
            $this->ai_engine_is_active = false;
        }



        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sintacs_mwai_export') {
            // Run your export function
            $this->export_chats();
        }

    }

    public function fetch_chats_with_pagination($per_page,$offset,$filters)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';

        // Start building the query
        $query = "SELECT * FROM {$table_name} order by id desc";

        // Add each filter to the query
        $filter_queries = [];
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $safe_value = esc_sql($value);
                $filter_queries[] = "{$key} = '{$safe_value}'";
            }
        }

        // If there are filter queries, add them to the main query
        if (!empty($filter_queries)) {
            $query .= ' WHERE ' . implode(' AND ',$filter_queries);
        }

        $query .= " LIMIT {$offset}, {$per_page}";

        // Get the results
        $results = $wpdb->get_results($query);
        return $results;
    }

    public function export_chats()
    {

        // Check if export-format is set in $_POST
        if (!isset($_POST['export-format']) || empty($_POST['export-format'])) {
            echo "Export format not set";
            return;
        }

        // Prüfen, ob "Export all" aktiviert ist
        if (isset($_POST['export-all']) && $_POST['export-all'] == '1') {
            // Alle Chat-IDs abrufen
            $chatIds = $this->get_all_chatIDs();

        } else {
            // Prüfen, ob Chat-IDs gesetzt sind
            if (!isset($_POST['chat_ids'])) {
                echo "Chat IDs not set";
                return;
            }
            $chatIds = $_POST['chat_ids'];
        }


        $exportType = $_POST['export-format'];
        $data = '';

        // Check the export type and call the appropriate method
        if ($exportType === 'csv') {
            $data = $this->exportChatsToCSV($chatIds);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="chat-export.csv"');
        } elseif ($exportType === 'json') {
            $data = $this->exportChatsToJSON($chatIds);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="chat-export.json"');
        } elseif ($exportType === 'pdf') {
            $this->exportChatsToPDF($chatIds);
        } else {
            // If the export type is unknown, throw an error
           echo "Unknown export type: $exportType";
        }

        // Output the data
        echo $data;
        exit;
    }

    public function get_total_chats()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        return $total;
    }

    public function add_admin_menu()
    {
        if (!$this->ai_engine_is_active) {
            add_menu_page(
                'AI Engine Export', // page_title
                'AI Engine Export', // menu_title
                'manage_options', // capability
                'chats_export', // menu_slug
                [$this,'show_export_page'], // function
                'dashicons-admin-comments', // icon_url
                100 // position
            );
        } else {
            // Add submenu page to Meow Apps main menu
            add_submenu_page(
                'meowapps-main-menu',
                'AI Engine Export',
                'AI Engine Export',
                'manage_options',
                'chats_export',
                [$this,'show_export_page']
            );
            // Add to tools page
            add_management_page(
                'AI Engine Export', // page_title
                'AI Engine Export', // menu_title
                'manage_options', // capability
                'chats_export', // menu_slug
                [$this, 'show_export_page'] // function
            );
        }
    }

    public function show_export_page()
    {

        // Check if the plugin
        if (!$this->ai_engine_is_active) {
            // Echo Wordpress default error hint
            echo '<p>This plugin requires the <a target="_blank" href="https://wordpress.org/plugins/ai-engine/">AI Engine plugin</a> to be installed and active.</p>';
            return;
        }

        $per_page = isset($_REQUEST['per-page']) ? max(1,intval($_REQUEST['per-page'])) : 20;
        $current_page = isset($_REQUEST['paged']) ? max(1,intval($_REQUEST['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $filters = [
            'ip' => $_REQUEST['ip-filter'],
            'date_from' => $_REQUEST['date-from-filter'],
            'date_to' => $_REQUEST['date-to-filter'],
            'userID' => $_REQUEST['userID-filter'],
            'botID' => $_REQUEST['botID-filter'],
            'chatID' => $_REQUEST['chatID-filter'],
        ];

        // Fetch data
        $chats = $this->fetch_chats_with_pagination($per_page,$offset,$filters);
        $total_chats = $this->get_total_chats();
        $total_pages = ceil($total_chats / $per_page);

        // Fetch filter data
        $userIDs = $this->get_all_userIDs();
        $botIDs = $this->get_all_botIDs();
        $chatIDs = $this->get_all_chatIDs();

        echo "<script>jQuery(document).ready(function($) {
     $('#select-all').click(function(event) {
        if(this.checked) {
            $('.select-chat').each(function() {
                this.checked = true;
            });
        } else {
            $('.select-chat').each(function() {
                this.checked = false;
            });
        }
        
    });
        $('#export').submit(function(e) {

            var exportFormat = $('#export-format').val();

            if (exportFormat === '') {
                alert('Please select an export format.');
                e.preventDefault(); // Verhindert das Absenden des Formulars
                return;
            }
            
            // is checkbox with ID export-all checked?
            var exportAll = $('#export-all').is(':checked');                        
            
            // Check if any chats are selected, but only if checkbox with name export-all is not selected
            if (!exportAll && $('.select-chat:checked').length === 0) {
                alert('Please select at least one chat.');
                e.preventDefault(); // Verhindert das Absenden des Formulars
            }

            if(exportAll) {
                $('.select-chat').each(function() {
                    this.checked = false;
                });
                $('#select-all').prop('checked', false);
            }
            
        });
        // On change sync the selected options of the two selects with name chat_ids
        $('.export-format').on('change', function() {      
            $('.export-format').val($(this).val());
        });
        
        // On change sync the checked status of the two export all checkboxes
        $('.export-all').on('change', function() {      
            console.log('change');
            $('.export-all').prop('checked', $(this).is(':checked'));
        });

    
});</script>";


        echo '<div class="wrap">';
        echo '<h1>Discussions Export</h1>';
        // Display per page dropdown
        echo '<form method="get" class="form-wrap">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';
        echo '<label for="per-page">Discussions per page: </label>';
        echo '<select name="per-page" id="per-page" class="postform">';
        foreach ([20,50,100,500] as $option) {
            $selected = selected($option,$per_page,false);
            echo "<option value=\"$option\"$selected>$option</option>";
        }
        echo '</select>';
        echo '<input type="submit" class="button" value="Refresh" />';
        echo '</form>';


        // Display filters
        echo '<form method="get" class="form-wrap">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';
        //echo '<input type="text" id="ip-filter" name="ip-filter" class="regular-text" placeholder="IP" value="' . esc_attr($_REQUEST['ip-filter']) . '" />';
        //echo '<input type="text" id="date-from-filter" name="date-from-filter" placeholder="Von Datum" value="' . $_REQUEST['date-from-filter'] . '" />';
        //echo '<input type="text" id="date-to-filter" name="date-to-filter" placeholder="Bis Datum" value="' . $_REQUEST['date-to-filter'] . '" />';
        //echo $this->create_dropdown('userID-filter', $userIDs, $_REQUEST['userID-filter'], 'UserID');
        //echo $this->create_dropdown('botID-filter', $botIDs, $_REQUEST['botID-filter'], 'BotID');
        //echo $this->create_dropdown('chatID-filter', $chatIDs, $_REQUEST['chatID-filter'], 'ChatID');
        //echo '<input type="submit" class="button button-primary" value="Filter" />';
        echo '</form>';

        // Display table
        echo '<div class="wrap">';


        echo '<form id="export" method="post" action="" target="_blank" class="form-wrap">';

        $this->display_pagination($current_page,$total_pages);


        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="select-all" /></td>';
        echo '<th>Messages</th>';
        echo '<th>ID</th>';
        echo '<th>UserID</th>';
        //echo '<th>IP</th>';

        //echo '<th>Extra</th>';
        echo '<th>BotID</th>';
        echo '<th>ChatID</th>';
        echo '<th>Created</th>';
        echo '<th>Updated</th>';
        //echo '<th>ThreadID</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';
        foreach ($chats as $chat) {
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" class="select-chat" name="chat_ids[]" value="' . esc_attr($chat->id) . '" /></th>';
            echo '<td>' . substr($this->format_messages($chat->messages),0,100) .
                '<br><a href="#" onclick="var chat = document.getElementById(\'chat-' . esc_html($chat->chatId) .
                '\'); chat.style.display = chat.style.display === \'none\' ? \'block\' : \'none\'; return false;">Show / Hide</a>' .
                '<div id="chat-' . esc_html($chat->chatId) . '" style="display: none; overflow: auto;">' .
                ($this->format_messages($chat->messages)) . '</div>' . '</td>';
            echo '<td>' . esc_html($chat->id) . '</td>';
            echo '<td>' . esc_html($chat->userId) . '</td>';
            //echo '<td>' . esc_html($chat->ip) . '</td>';

            //echo '<td>' . esc_html($chat->extra) . '</td>';
            echo '<td>' . esc_html($chat->botId) . '</td>';
            echo '<td>' . esc_html($chat->chatId) . '</td>';
            echo '<td>' . esc_html($chat->created) . '</td>';
            echo '<td>' . esc_html($chat->updated) . '</td>';
            //echo '<td>' . esc_html($chat->threadId) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        $this->display_pagination($current_page,$total_pages);
        echo '</form>';
        echo '</div>';

        // Display pagination


    }

    public function display_pagination($current_page,$total_pages)
    {
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged','%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
            'type' => 'array',
            'show_all' => true // shows all page numbers
        ));
        echo '<div class="tablenav">';
        // Display dropdown for export
        echo '<div class="alignleft actions bulkactions">';
        //echo '<form method="post" action="" class="form-wrap">';
        //echo '<label for="export-format">Export format: </label>';
        echo '<select name="export-format" id="export-format" class="postform export-format">';
        echo '<option value="">Export as...</option>';
        foreach (['pdf','json','csv'] as $option) {
            echo "<option value=\"$option\">" . strtoupper($option) . "</option>";
        }
        echo '</select>';

        // Export all option
        echo '<div style="float: left">';
        echo '<label style="display: block; float: left; margin-right: 5px; margin-top: 3px" class="" for="export-all">Export all</label>';
        echo '<input style="margin-top: 5px" type="checkbox" name="export-all" class="export-all" id="export-all" value="1">';
        echo '</div>';

        echo '<input type="submit" class="button button-primary" value="' . __('Export') . '" id="export-button"  />';
        echo '<input type="hidden" name="action" value="sintacs_mwai_export" />';
        //echo '</form>';
        echo '</div>';

        if ($page_links) {
            echo '<div class="aligncenter actions">';
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . $this->get_total_chats() . ' ' . __('Entries') . '</span>';
            echo '<span class="pagination-links">';

            // Previous and first page links
            $page_prev = $current_page - 1;
            echo '<a class="first-page button ' . ($current_page == 1 ? 'disabled' : 'page-numbers prev') . '" href="' . add_query_arg('paged', 1) . '"><span class="screen-reader-text">Erste Seite</span><span aria-hidden="true">«</span></a>';
            echo '<a class="prev-page button ' . ($current_page == 1 ? 'disabled' : 'page-numbers prev') . '" href="' . add_query_arg('paged', max($page_prev, 1)) . '"><span class="screen-reader-text">Vorherige Seite</span><span aria-hidden="true">‹</span></a>';

            // Current page input
            echo '<span class="paging-input">';
            echo '<label for="current-page-selector" class="screen-reader-text">Aktuelle Seite</label>';
            echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $current_page . '" size="1">';
            echo '<span class="tablenav-paging-text"> of <span class="total-pages">' . $total_pages . '</span></span>';
            echo '</span>';

            // Next and last page links
            echo '<a class="next-page button ' . ($current_page == $total_pages ? 'disabled' : 'page-numbers next') . '" href="' . add_query_arg('paged',$current_page + 1) . '"><span class="screen-reader-text">Nächste Seite</span><span aria-hidden="true">›</span></a>';
            echo '<a class="last-page button ' . ($current_page == $total_pages ? 'disabled' : 'page-numbers next') . '" href="' . add_query_arg('paged',$total_pages) . '"><span class="screen-reader-text">Letzte Seite</span><span aria-hidden="true">»</span></a>';

            echo '</span></div>';

        }
        echo '</div>';
    }

    private function create_dropdown($name,$options,$selectedOption,$placeholder)
    {
        $html = '<select name="' . $name . '">';
        $html .= '<option value="">' . $placeholder . '</option>';

        foreach ($options as $option) {
            if ($selectedOption == $option) {
                $html .= '<option value="' . $option . '" selected>' . $option . '</option>';
            } else {
                $html .= '<option value="' . $option . '">' . $option . '</option>';
            }
        }

        $html .= '</select>';
        return $html;
    }

    public function get_chat_data($chat_ids = null)
    {
        global $wpdb;

        // Prepare the SQL query
        $table_name = $wpdb->prefix . 'mwai_chats';
        $sql = "SELECT * FROM `$table_name`";

        // If chat_ids are provided, add a WHERE clause to the query
        if (!empty($chat_ids)) {
            $ids = implode(',',array_map(function ($id) {
                return intval($id);
            },$chat_ids));
            $sql .= " WHERE `id` IN ($ids)";
        }
//echo 'sql: ' . $sql;
        // Execute the query
        $chats = $wpdb->get_results($sql,ARRAY_A);
//print_r($chats);
        return $chats;
    }

    // Export chats to CSV
    public function exportChatsToCSV($chatIds)
    {

//printf( '<pre>%s</pre>', print_r( $chatIds, 1 ) );


        // Fetch chat data based on chat IDs
        $chats = $this->get_chat_data($chatIds);

        // Define headers
        $output = "ConversationID|UserID|BotID|DateTime|UserQuestion|AssistantAnswer\n";

        foreach ($chats as $chat) {
            // messages is JSON, decode it into a PHP array
            $messages = json_decode($chat['messages'],true);

            // Initialize previous user message
            $previousUserMessage = '';

            foreach ($messages as $message) {
                // Format date to dd/mm/yyyy
                $dateTime = date('d/m/Y H:i:s',strtotime($chat['created']));

                // Remove new lines from message
                $message['content'] = str_replace("\n"," ",$message['content']);

                if ($message['role'] === 'user' && !empty($message['content'])) {
                    // Save user message to pair with the next assistant message
                    $previousUserMessage = $message['content'];
                } elseif ($message['role'] === 'assistant' && !empty($previousUserMessage) && !empty($message['content'])) {
                    // Output a row for each question-answer pair
                    $row = [
                        $chat['chatId'],
                        $chat['userId'],
                        $chat['botId'],
                        $dateTime,
                        $previousUserMessage,
                        $message['content']
                    ];

                    // Convert array to CSV string and append to output
                    $output .= implode('|',$row) . "\n";

                    // Reset previous user message
                    $previousUserMessage = '';
                }
            }
        }

        return $output;
    }


// Export chats to JSON
    public function exportChatsToJSON($chatIds)
    {
        // You need to implement getChatData to fetch chat data based on chat IDs
        $chats = $this->get_chat_data($chatIds);

        // Convert chat data to JSON
        $output = json_encode($chats);

        return $output;
    }

    public function exportChatsToPDF($chatIds)
    {
        require_once('vendor/tecnickcom/tcpdf/tcpdf.php'); // Pfad zur TCPDF-Bibliothek
        require_once('mwaiTcpdf.php');

        $pdf = new MwaiTCPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->AddPage();

        $chats = $this->get_chat_data($chatIds);

        foreach ($chats as $chat) {
            $messages = json_decode($chat['messages'],true);

            // Count how many messages were sent by the user
            $userMessagesCount = count(array_filter($messages,function ($message) {
                return $message['role'] === 'user';
            }));

            // Convert date to German format
            $createdDate = strtotime($chat['created']);
            $wochentagKurz = $this->getGermanDayShortName(date('D',$createdDate));
            $createdDeutsch = $wochentagKurz . ', ' . date('d.m.Y H:i',$createdDate);

            // Header information for each chat
            $headerHtml = "<h2>Chat Details</h2>"
                . "<strong>ChatID:</strong> {$chat['chatId']}<br>"
                . "<strong>Created:</strong> {$createdDeutsch}<br>"
                . "<strong>User Messages :</strong> {$userMessagesCount}<br>";
            $pdf->writeHTMLCell(0,0,'','',$headerHtml,0,1,0,true,'',true);

            foreach ($messages as $message) {
                // Add role before message
                $rolePrefix = ucfirst($message['role']) . ': ';
                // Set color and alignment based on role
                if ($message['role'] === 'user') {
                    $pdf->SetTextColor(0,0,139); // Dunkelblau für User
                    $align = 'L';
                } else {
                    $pdf->SetTextColor(0,0,0); // Schwarz für Assistant
                    $align = 'R';
                }
                // Use MultiCell for automatic wrapping and add the role before the message
                $pdf->MultiCell(0,10,$rolePrefix . $message['content'],0,$align,false,1);
                // Add an additional line break after each message to ensure at least one line of spacing
                $pdf->Ln(5);
            }

            // Add more space after each chat
            $pdf->Ln(10);
            // and a horizontal dividing line
            $pdf->Line(10,0,200,0);
        }

        // View PDF in browser
        $pdf->Output('chat-export.pdf','I');
    }

    private function get_all_userIDs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        $results = $wpdb->get_col("SELECT DISTINCT userId FROM {$table_name}");
        return $results;
    }

    private function get_all_botIDs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        $results = $wpdb->get_col("SELECT DISTINCT botId FROM {$table_name}");
        return $results;
    }

    private function get_all_chatIDs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        $results = $wpdb->get_col("SELECT DISTINCT id FROM {$table_name} order by id desc");
//printf( '<pre>%s</pre>', print_r( $results, 1 ) );
        return $results;
    }


    protected function format_messages($messages)
    {
        $messages = json_decode($messages,true);
        $formatted_messages = '';

        $i = 0;
        foreach ($messages as $message) {

            $formatted_message = ucfirst(esc_html($message['role'])) . ': ' . esc_html($message['content']) . '<br><br>';
            $formatted_message = ($i == 0) ? '<strong>' . $formatted_message . '</strong>' : $formatted_message;

            $formatted_messages .= $formatted_message;

            $i++;
        }

        return $formatted_messages;
    }


    /**
     * Add custom query vars.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public function add_query_vars($vars)
    {
        // Add custom query vars
        $vars[] = 'per-page';
        $vars[] = 'paged';
        $vars[] = 'ip-filter';
        $vars[] = 'date-from-filter';
        $vars[] = 'date-to-filter';
        $vars[] = 'userID-filter';
        $vars[] = 'botID-filter';
        $vars[] = 'chatID-filter';
        $vars[] = 'export-format';
        $vars[] = 'chat-ids';
        $vars[] = 'action';

        return $vars;
    }

    private function enqueue_datepicker_assets()
    {
        wp_enqueue_script('sintacs-mwai-jquery-ui-datepicker');
        wp_enqueue_style('sintacs-mwai-jquery-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
    }

    private function getGermanDayShortName($day)
    {
        $days = [
            'Mon' => 'Mo',
            'Tue' => 'Di',
            'Wed' => 'Mi',
            'Thu' => 'Do',
            'Fri' => 'Fr',
            'Sat' => 'Sa',
            'Sun' => 'So',
        ];

        return $days[$day] ?? 'Unbekannt';
    }
}

$exportChats = new SintacsMwaiExportChats();