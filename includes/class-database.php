<?php

namespace SintacsMwaiExportChats;

class Database {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mwai_chats';
    }

    private function getTableName() : string {
        return $this->table_name;
    }

    public function isAiEngineActive() : bool {
        global $wpdb;
        $tableName = $this->table_name;
        return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tableName ) ) == $tableName;
    }

    public function fetchChatsWithPagination( int $perPage, int $offset, array $filters ) : array {
        global $wpdb;
        $table_name = $this->getTableName();
        $where_clauses = [];
        $query_params = [];
        $where_sql = ( $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '' );
        $sql = $wpdb->prepare( "SELECT * FROM `{$table_name}` {$where_sql} ORDER BY created DESC LIMIT %d OFFSET %d", array_merge( $query_params, [$perPage, $offset] ) );
        $results = $wpdb->get_results( $sql, ARRAY_A );
        if ( $wpdb->last_error ) {
            error_log( "Database error in fetchChatsWithPagination: " . $wpdb->last_error );
        }
        return ( $results !== null ? $results : [] );
    }

    public function getTotalChats( array $filters ) : int {
        global $wpdb;
        $table_name = $this->getTableName();
        $where_clauses = $this->buildWhereClausesFromFilters( $filters );
        $where_sql = ( $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '' );
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} {$where_sql}" ) );
    }

    public function getTotalMessages( array $filters ) : int {
        global $wpdb;
        $table_name = $this->getTableName();
        $where_clauses = $this->buildWhereClausesFromFilters( $filters );
        $where_sql = ( $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '' );
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(JSON_LENGTH(messages)) as total_messages FROM {$table_name} {$where_sql}" ) );
    }

    private function buildWhereClausesFromFilters( array $filters ) : array {
        global $wpdb;
        $where_clauses = [];
        if ( !empty( $filters['date_from'] ) ) {
            $where_clauses[] = $wpdb->prepare( "created >= %s", $filters['date_from'] );
        }
        if ( !empty( $filters['date_to'] ) ) {
            $where_clauses[] = $wpdb->prepare( "created <= %s", $filters['date_to'] );
        }
        if ( !empty( $filters['userID'] ) ) {
            if ( $filters['userID'] == 'guest' || $filters['userID'] == '' ) {
                $where_clauses[] = "userId IS NULL";
            } else {
                $where_clauses[] = $wpdb->prepare( "userId = %s", $filters['userID'] );
            }
        }
        if ( !empty( $filters['chatID'] ) ) {
            $where_clauses[] = $wpdb->prepare( "chatId = %s", $filters['chatID'] );
        }
        if ( !empty( $filters['botID'] ) ) {
            $where_clauses[] = $wpdb->prepare( "botId = %s", $filters['botID'] );
        }
        return $where_clauses;
    }

    public function getAllUserIDs() : array {
        global $wpdb;
        $tableName = $this->table_name;
        $sql = "SELECT DISTINCT userId FROM `{$tableName}` WHERE userId != %s";
        $prepared_sql = $wpdb->prepare( $sql, '' );
        return array_filter( $wpdb->get_col( $prepared_sql ) );
    }

    public function getAllBotIDs() : array {
        global $wpdb;
        $tableName = $this->table_name;
        $sql = "SELECT DISTINCT botId FROM `{$tableName}` WHERE botId != %s";
        $prepared_sql = $wpdb->prepare( $sql, '' );
        return array_filter( $wpdb->get_col( $prepared_sql ) );
    }

    public function getAllChatIDs() : array {
        global $wpdb;
        $tableName = $this->table_name;
        $sql = "SELECT DISTINCT chatId FROM `{$tableName}` WHERE chatId != %s ORDER BY chatId DESC";
        $prepared_sql = $wpdb->prepare( $sql, '' );
        return array_filter( $wpdb->get_col( $prepared_sql ) );
    }

    public function getChatData( array $chatIds = null ) : array {
        global $wpdb;
        $tableName = $this->table_name;
        if ( !empty( $chatIds ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $chatIds ), '%d' ) );
            $chats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tableName}` WHERE `id` IN ({$placeholders})", ...$chatIds ), ARRAY_A );
        } else {
            $chats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tableName}`" ), ARRAY_A );
        }
        foreach ( $chats as &$chat ) {
            $chatbotSettings = $this->getChatbotSettings( $chat['botId'] );
            $chat['botName'] = $chatbotSettings['name'] ?? '';
        }
        return $chats;
    }

    public function getChatbotSettings( string $botId = null ) : array {
        $chatbots = get_option( 'mwai_chatbots', [] );
        if ( $botId ) {
            foreach ( $chatbots as $chatbot ) {
                if ( $chatbot['botId'] === $botId ) {
                    return $chatbot;
                }
            }
        }
        return $chatbots;
    }

    public function countTotalMessages() : int {
        global $wpdb;
        $tableName = $this->table_name;
        $chats = $wpdb->get_results( $wpdb->prepare( "SELECT messages FROM %s", $tableName ), ARRAY_A );
        $totalMessages = 0;
        foreach ( $chats as $chat ) {
            $messages = json_decode( $chat['messages'], true );
            $totalMessages += count( $messages );
        }
        return $totalMessages;
    }

    public function getAllChats() : array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %s", $table_name ), ARRAY_A );
    }

    public function getChatsById( array $chatIds ) : array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwai_chats';
        $placeholders = implode( ',', array_fill( 0, count( $chatIds ), '%d' ) );
        error_log( 'chatIds2: ' . print_r( $chatIds, true ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id IN ({$placeholders})", ...$chatIds ), ARRAY_A );
    }

}
