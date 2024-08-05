<?php

namespace SintacsMwaiExportChats;

use ChatExporter;
class AdminPage {
    private ChatExporter $chatExporter;

    private Database $database;

    private $perPage;

    public function __construct( ChatExporter $chatExporter, Database $database ) {
        $this->chatExporter = $chatExporter;
        $this->database = $database;
        $this->perPage = ( isset( $_REQUEST['per-page'] ) ? max( 1, intval( $_REQUEST['per-page'] ) ) : 20 );
    }

    public function render() : void {
        if ( !$this->database->isAiEngineActive() ) {
            echo '<p>' . __( 'This plugin requires the <a target="_blank" href="https://wordpress.org/plugins/ai-engine/">AI Engine plugin</a> to be installed and active.', 'sintacs-mwai-export-chats' ) . '</p>';
            return;
        }
        $currentPage = ( isset( $_REQUEST['paged'] ) ? max( 1, intval( $_REQUEST['paged'] ) ) : 1 );
        $offset = ($currentPage - 1) * $this->perPage;
        $filters = [
            'date_from' => $_REQUEST['date-from-filter'] ?? '',
            'date_to'   => $_REQUEST['date-to-filter'] ?? '',
            'userID'    => $_REQUEST['userID-filter'] ?? '',
            'chatID'    => $_REQUEST['chatID-filter'] ?? '',
            'botID'     => $_REQUEST['botID-filter'] ?? '',
        ];
        $chats = $this->database->fetchChatsWithPagination( $this->perPage, $offset, $filters );
        $totalChats = $this->database->getTotalChats( $filters );
        $totalMessages = $this->database->getTotalMessages( $filters );
        $userIDs = $this->database->getAllUserIDs();
        $botIDs = $this->database->getAllBotIDs();
        $chatIDs = $this->database->getAllChatIDs();
        $this->renderJavaScript();
        $this->renderHeader();
        // Start the main form
        echo '<form id="main-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="form-wrap">';
        wp_nonce_field( 'sintacs_mwai_export_chats', 'sintacs_mwai_nonce' );
        echo '<input type="hidden" name="action" value="sintacs_mwai_export_chats" />';
        // Filters date, user, bot, chat
        echo '<input type="hidden" name="date-from-filter" value="' . esc_attr( $_REQUEST['date-from-filter'] ?? '' ) . '" />';
        echo '<input type="hidden" name="date-to-filter" value="' . esc_attr( $_REQUEST['date-to-filter'] ?? '' ) . '" />';
        echo '<input type="hidden" name="userID-filter" value="' . esc_attr( $_REQUEST['userID-filter'] ?? '' ) . '" />';
        echo '<input type="hidden" name="botID-filter" value="' . esc_attr( $_REQUEST['botID-filter'] ?? '' ) . '" />';
        echo '<input type="hidden" name="chatID-filter" value="' . esc_attr( $_REQUEST['chatID-filter'] ?? '' ) . '" />';
        $this->renderPerPageForm( $this->perPage );
        $this->renderFiltersForm( $userIDs, $botIDs, $chatIDs );
        $this->renderExportForm(
            $currentPage,
            $totalChats,
            $totalMessages,
            $chats
        );
        echo '</form>';
    }

    private function renderJavaScript() : void {
        echo "<script>\r\n            jQuery(document).ready(function(\$) {\r\n\r\n                \$('#select-all').click(function(event) {\r\n                    \$('.select-chat').prop('checked', this.checked);\r\n                });\r\n\r\n                \$('#reset-filters').click(function() {\r\n                    console.log('reset filters');\r\n                    resetFilters();\r\n                });\r\n                \r\n                \$('.select-chat').click(function() {\r\n                    if(\$('.select-chat:checked').length == \$('.select-chat').length) {\r\n                        \$('#select-all').prop('checked', true);\r\n                    } else {\r\n                        \$('#select-all').prop('checked', false);\r\n                    }\r\n                });\r\n                \r\n                // Synchronize per-page value\r\n                \$('#per-page').change(function() {\r\n                    \$('#per-page-hidden').val(\$(this).val());\r\n                });\r\n\r\n                // Synchronize export_all checkboxes\r\n                \$('.export_all').change(function() {\r\n                    var isChecked = \$(this).is(':checked');\r\n                    \$('.export_all').prop('checked', isChecked);\r\n                    updateExportButtonText();\r\n                });\r\n\r\n                // Watch for changes in filters\r\n                \$('#date-from-filter, #date-to-filter, #userID-filter, #botID-filter, #chatID-filter').change(function() {\r\n                    updateExportButtonText();\r\n                });\r\n\r\n                \$('#export-button').on('click', function(e) {\r\n                    var exportFormat = \$('#export-format').val();\r\n                    if (exportFormat === '') {\r\n                        alert('" . __( 'Please select an export format.', 'sintacs-mwai-export-chats' ) . "');\r\n                        e.preventDefault();\r\n                        return;\r\n                    }\r\n                    \r\n                    var exportAll = \$('.export_all').is(':checked');\r\n                    if (!exportAll && \$('.select-chat:checked').length === 0) {\r\n                        alert('" . __( 'Please select at least one chat.', 'sintacs-mwai-export-chats' ) . "');\r\n                        e.preventDefault();\r\n                    }\r\n\r\n                    if(exportAll) {\r\n                        \$('.select-chat, #select-all').prop('checked', false);\r\n                    }\r\n                });\r\n                \r\n                \$('.export-format').on('change', function() {      \r\n                    \$('.export-format').val(\$(this).val());\r\n                });\r\n\r\n                function updateExportButtonText() {\r\n                    var exportAll = \$('.export_all').is(':checked');\r\n                    var filtersSet = areFiltersSet();\r\n                    console.log(filtersSet);\r\n                    var buttonText = filtersSet ? '" . __( 'Export (filtered)', 'sintacs-mwai-export-chats' ) . "' : '" . __( 'Export', 'sintacs-mwai-export-chats' ) . "';\r\n                    \$('.export-button').val(buttonText);\r\n                }\r\n\r\n                function areFiltersSet() {\r\n                    return \$('#date-from-filter').val() !== '' || \$('#date-to-filter').val() !== '' || \$('#userID-filter').val() !== '' || \$('#botID-filter').val() !== '' || \$('#chatID-filter').val() !== '';\r\n                }\r\n\r\n                updateExportButtonText();\r\n                \r\n                function resetFilters() {\r\n                    \r\n                    \$('#date-from-filter').value = '';\r\n                    \$('#date-to-filter').value = '';\r\n                    \$('#userID-filter').selectedIndex = 0;\r\n                    \$('#botID-filter').selectedIndex = 0;\r\n                    \$('#chatID-filter').selectedIndex = 0;\r\n                    \$('#per-page-hidden').value = 20; // Default per-page value\r\n                    document.forms[0].submit();\r\n                }\r\n\r\n                ";
        if ( !sintacs_mwai_export()->is__premium_only() || !sintacs_mwai_export()->can_use_premium_code() ) {
            echo "\r\n                // Disable filters for non-premium users\r\n                \$('#date-from-filter, #date-to-filter, #userID-filter, #botID-filter, #chatID-filter').prop('disabled', true);\r\n                \r\n                // Add message about premium feature\r\n                \$('<div class=\"premium-message\" style=\"margin-top: 10px;\">' + \r\n                    '" . esc_js( __( 'Filters are only available in the premium version. ', 'sintacs-mwai-export-chats' ) ) . "' +\r\n                    '<a href=\"https://" . esc_js( get_site_url() ) . "/wp-admin/admin.php?page=chats_export-pricing\" target=\"_blank\">" . esc_js( __( 'Upgrade to premium', 'sintacs-mwai-export-chats' ) ) . "</a>' +\r\n                    '</div>').insertAfter('#chat-filter');\r\n\r\n                // Show alert when trying to use filters\r\n                \$('#do-filter, #reset-filters, #date-from-filter, #date-to-filter, #userID-filter, #botID-filter, #chatID-filter').click(function(e) {\r\n                    e.preventDefault();\r\n                    alert('" . esc_js( __( 'Filters are only available in the premium version. Upgrade to premium to use filters.', 'sintacs-mwai-export-chats' ) ) . "');\r\n                });\r\n            ";
        }
        echo "\r\n            });\r\n\r\n        </script>";
    }

    private function renderHeader() : void {
        echo '<div class="wrap">';
        echo '<h1>' . __( 'Discussions Export', 'sintacs-mwai-export-chats' ) . '</h1>';
    }

    private function renderPerPageForm( int $perPage ) : void {
        echo '<label for="per-page">' . __( 'Discussions per page:', 'sintacs-mwai-export-chats' ) . ' </label>';
        echo '<select name="per-page" id="per-page" class="postform">';
        foreach ( [
            20,
            50,
            100,
            500
        ] as $option ) {
            $selected = selected( $option, $perPage, false );
            echo "<option value=\"{$option}\"{$selected}>{$option}</option>";
        }
        echo '</select>';
        echo '<button type="submit" name="refresh" class="button" value="1">' . __( 'Refresh', 'sintacs-mwai-export-chats' ) . '</button>';
    }

    private function renderFiltersForm( array $userIDs, array $botIDs, array $chatIDs ) : void {
        echo '<div id="chat-filter" style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; margin-bottom: 15px; margin-top: 15px;">';
        echo '<input type="hidden" name="per-page" id="per-page-hidden" value="' . esc_attr( $this->perPage ) . '" />';
        // Date From
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="date-from-filter">' . __( 'Date From:', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<input type="date" id="date-from-filter" name="date-from-filter" value="' . esc_attr( $_REQUEST['date-from-filter'] ?? '' ) . '" />';
        echo '</div>';
        // Date To
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="date-to-filter">' . __( 'Date To:', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<input type="date" id="date-to-filter" name="date-to-filter" value="' . esc_attr( $_REQUEST['date-to-filter'] ?? '' ) . '" />';
        echo '</div>';
        // User ID
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="userID-filter">' . __( 'User ID:', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<select id="userID-filter" name="userID-filter">';
        echo '<option value="">' . __( 'All Users', 'sintacs-mwai-export-chats' ) . '</option>';
        foreach ( $userIDs as $userID ) {
            $selected = selected( $userID, $_REQUEST['userID-filter'] ?? '', false );
            echo "<option value=\"{$userID}\"{$selected}>{$userID}</option>";
        }
        // Add Guest as option
        $selectedOption = $_REQUEST['userID-filter'] ?? '';
        $guestSelected = ( $selectedOption === 'guest' ? ' selected' : '' );
        echo "<option value=\"guest\"{$guestSelected}>" . __( 'Guest', 'sintacs-mwai-export-chats' ) . "</option>";
        echo '</select>';
        echo '</div>';
        // Bot ID
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="botID-filter">' . __( 'Bot ID:', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<select id="botID-filter" name="botID-filter">';
        echo '<option value="">' . __( 'All Bots', 'sintacs-mwai-export-chats' ) . '</option>';
        foreach ( $botIDs as $botID ) {
            $selected = selected( $botID, $_REQUEST['botID-filter'] ?? '', false );
            echo "<option value=\"{$botID}\"{$selected}>{$botID}</option>";
        }
        echo '</select>';
        echo '</div>';
        // Chat ID
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<label for="chatID-filter">' . __( 'Chat ID:', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<select id="chatID-filter" name="chatID-filter">';
        echo '<option value="">' . __( 'All Chats', 'sintacs-mwai-export-chats' ) . '</option>';
        foreach ( $chatIDs as $chatID ) {
            $selected = selected( $chatID, $_REQUEST['chatID-filter'] ?? '', false );
            echo "<option value=\"{$chatID}\"{$selected}>{$chatID}</option>";
        }
        echo '</select>';
        echo '</div>';
        echo '<button id="do-filter" type="submit" name="filter" class="button" value="1">' . __( 'Filter', 'sintacs-mwai-export-chats' ) . '</button>';
        echo '</div>';
    }

    private function renderExportForm(
        int $currentPage,
        int $totalChats,
        int $totalMessages,
        array $chats
    ) : void {
        // Top tablenav with export options
        echo '<div class="tablenav top">';
        $this->renderPagination(
            $currentPage,
            $totalChats,
            $totalMessages,
            $chats
        );
        $this->renderExportOptions();
        echo '</div>';
        // Check if $chats is an array and not empty
        if ( is_array( $chats ) && !empty( $chats ) ) {
            $this->renderChatsTable( $chats );
        } else {
            echo '<p>' . __( 'No chats found.', 'sintacs-mwai-export-chats' ) . '</p>';
        }
        // Bottom tablenav with export options
        echo '<div class="tablenav bottom">';
        $this->renderPagination(
            $currentPage,
            $totalChats,
            $totalMessages,
            $chats
        );
        $this->renderExportOptions();
        echo '</div>';
    }

    private function renderChatsTable( array $chats ) : void {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="select-all" /></td>';
        echo '<th>' . __( 'Messages', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( '# Messages', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( 'ID', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( 'UserID', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( 'Bot ID', 'sintacs-mwai-export-chats' ) . ' (' . __( 'Bot Name', 'sintacs-mwai-export-chats' ) . ')</th>';
        echo '<th>' . __( 'ChatID', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( 'Created', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '<th>' . __( 'Updated', 'sintacs-mwai-export-chats' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ( $chats as $chat ) {
            $chatbotSettings = $this->getChatbotSettings( $chat['botId'] );
            $messageCount = $this->getMessageCount( $chat['messages'] );
            $userIdDisplay = ( is_null( $chat['userId'] ) ? __( 'Guest', 'sintacs-mwai-export-chats' ) : esc_html( $chat['userId'] ) );
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" class="select-chat" name="chat_ids[]" value="' . esc_attr( $chat['id'] ) . '" /></th>';
            echo '<td>' . $this->formatMessages( $chat['messages'], true ) . '<br><a href="#" onclick="var chat = document.getElementById(\'chat-' . esc_attr( $chat['id'] ) . '\'); chat.style.display = chat.style.display === \'none\' ? \'block\' : \'none\'; return false;">' . __( 'Show / Hide', 'sintacs-mwai-export-chats' ) . '</a>' . '<div id="chat-' . esc_attr( $chat['id'] ) . '" style="display: none; overflow: auto;">' . $this->formatMessages( $chat['messages'] ) . '</div>' . '</td>';
            echo '<td>' . esc_html( $messageCount ) . '</td>';
            echo '<td>' . esc_html( $chat['id'] ) . '</td>';
            echo '<td>' . $userIdDisplay . '</td>';
            echo '<td>' . esc_html( $chat['botId'] ) . '  (' . esc_html( $chatbotSettings['name'] ) . ')</td>';
            echo '<td>' . esc_html( $chat['chatId'] ) . '</td>';
            echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $chat['created'] ) ) ) . '</td>';
            echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $chat['updated'] ) ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    private function renderExportOptions() : void {
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="export_format" id="export-format" class="postform export-format">';
        echo '<option value="">' . __( 'Export as...', 'sintacs-mwai-export-chats' ) . '</option>';
        foreach ( ['pdf', 'json', 'csv'] as $option ) {
            echo "<option value=\"{$option}\">" . strtoupper( $option ) . "</option>";
        }
        echo '</select>';
        echo '<div style="float: left; margin-left: 5px;">';
        echo '<label style="display: block; float: left; margin-right: 5px; margin-top: 5px" for="export_all">' . __( 'Export all', 'sintacs-mwai-export-chats' ) . '</label>';
        echo '<input style="margin-top: 7px" type="checkbox" name="export_all" class="export_all" value="1">';
        echo '</div>';
        echo '<input type="submit" class="button button-primary export-button" value="' . __( 'Export', 'sintacs-mwai-export-chats' ) . '" id="export-button" style="margin-left: 5px;" />';
        echo '</div>';
    }

    private function formatMessages( $messages, $truncate = false ) : string {
        $decodedMessages = json_decode( $messages, true );
        if ( !is_array( $decodedMessages ) ) {
            return esc_html( substr( $messages, 0, 100 ) ) . '...';
        }
        $formattedMessages = '';
        foreach ( $decodedMessages as $index => $message ) {
            if ( !isset( $message['role'] ) || !isset( $message['content'] ) ) {
                continue;
            }
            $formattedMessage = ucfirst( esc_html( $message['role'] ) ) . ': ' . esc_html( $message['content'] ) . '<br><br>';
            $formattedMessage = ( $index == 0 ? '<strong>' . $formattedMessage . '</strong>' : $formattedMessage );
            $formattedMessages .= $formattedMessage;
            if ( $truncate && strlen( $formattedMessages ) > 100 ) {
                return substr( $formattedMessages, 0, 100 ) . '...';
            }
        }
        return $formattedMessages;
    }

    private function getMessageCount( $messages ) : int {
        $decodedMessages = json_decode( $messages, true );
        return ( is_array( $decodedMessages ) ? count( $decodedMessages ) : 0 );
    }

    private function renderPagination(
        int $currentPage,
        int $totalChats,
        int $totalMessages,
        array $chats
    ) : void {
        $totalPages = ceil( $totalChats / $this->perPage );
        echo '<div class="tablenav-pages">';
        echo '<span class="displaying-num">' . $totalChats . ' ' . __( 'Discussions', 'sintacs-mwai-export-chats' ) . ' | ' . $totalMessages . ' ' . __( 'Messages', 'sintacs-mwai-export-chats' ) . '</span>';
        if ( $totalPages > 1 ) {
            echo '<span class="pagination-links">';
            // First page link
            $firstPageClass = ( $currentPage == 1 ? 'disabled' : '' );
            echo '<a class="first-page button ' . $firstPageClass . '" href="' . $this->getPageUrl( 1 ) . '" style="margin-right: 4px;"><span class="screen-reader-text">' . __( 'First page', 'sintacs-mwai-export-chats' ) . '</span><span aria-hidden="true">&laquo;</span></a>';
            // Previous page link
            $prevPage = max( 1, $currentPage - 1 );
            $prevPageClass = ( $currentPage == 1 ? 'disabled' : '' );
            echo '<a class="prev-page button ' . $prevPageClass . '" href="' . $this->getPageUrl( $prevPage ) . '" style="margin-right: 4px;"><span class="screen-reader-text">' . __( 'Previous page', 'sintacs-mwai-export-chats' ) . '</span><span aria-hidden="true">&lsaquo;</span></a>';
            // Current page input
            echo '<span class="paging-input" style="margin: 0 4px;">';
            echo '<span class="tablenav-paging-text">' . $currentPage . ' ' . __( 'of', 'sintacs-mwai-export-chats' ) . ' <span class="total-pages">' . $totalPages . '</span></span>';
            echo '</span>';
            // Next page link
            $nextPage = min( $totalPages, $currentPage + 1 );
            $nextPageClass = ( $currentPage == $totalPages ? 'disabled' : '' );
            echo '<a class="next-page button ' . $nextPageClass . '" href="' . $this->getPageUrl( $nextPage ) . '" style="margin-left: 4px;"><span class="screen-reader-text">' . __( 'Next page', 'sintacs-mwai-export-chats' ) . '</span><span aria-hidden="true">&rsaquo;</span></a>';
            // Last page link
            $lastPageClass = ( $currentPage == $totalPages ? 'disabled' : '' );
            echo '<a class="last-page button ' . $lastPageClass . '" href="' . $this->getPageUrl( $totalPages ) . '" style="margin-left: 4px;"><span class="screen-reader-text">' . __( 'Last page', 'sintacs-mwai-export-chats' ) . '</span><span aria-hidden="true">&raquo;</span></a>';
            echo '</span>';
        }
        echo '</div>';
    }

    private function getPageUrl( int $page ) : string {
        $currentUrl = add_query_arg( null, null );
        return add_query_arg( 'paged', max( 1, $page ), remove_query_arg( 'paged', $currentUrl ) );
    }

    private function getChatbotSettings( string $botId ) : array {
        $chatbots = get_option( 'mwai_chatbots', [] );
        foreach ( $chatbots as $chatbot ) {
            if ( $chatbot['botId'] === $botId ) {
                return $chatbot;
            }
        }
        return [
            'name' => __( 'Unknown', 'sintacs-mwai-export-chats' ),
        ];
    }

}
