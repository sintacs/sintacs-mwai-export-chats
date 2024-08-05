<?php

class ChatExporter
{
    private \SintacsMwaiExportChats\Database $database;

    public function __construct(\SintacsMwaiExportChats\Database $database)
    {
        $this->database = $database;
    }

    public function export(string $exportFormat,?array $chatIds): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.','sintacs-mwai-export-chats'));
        }

        $chats = $chatIds === null ? $this->database->getAllChats() : $this->database->getChatsById($chatIds);

        switch ($exportFormat) {
            case 'csv':
                $this->exportCSV($chats);
                break;
            case 'json':
                $this->exportJSON($chats);
                break;
            case 'pdf':
                $this->exportPDF($chats);
                break;
            default:
                wp_die(__('Invalid export format','sintacs-mwai-export-chats'));
        }
    }

    private function exportCSV(array $chats): void
    {
        $filename = 'chat-export-' . gmdate('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output','w');

        fputcsv($output,['Chat ID','User ID','Bot ID','IP','Date','Message Role','Message Content','# Messages']);

        foreach ($chats as $chat) {
            $messages = json_decode($chat['messages'],true);
            if (!is_array($messages)) {
                continue; // Skip this chat if messages is not a valid array
            }

            $messageCount = count($messages);

            foreach ($messages as $message) {
                if (!isset($message['role']) || !isset($message['content'])) {
                    continue; // Skip this message if it doesn't have role or content
                }

                $createdDate = isset($chat['created']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'),strtotime($chat['created'])) : '';

                fputcsv($output,[
                    $chat['chatId'] ?? '',
                    $chat['userId'] ?? '',
                    $chat['botId'] ?? '',
                    $chat['ip'] ?? '',
                    $createdDate,
                    $message['role'],
                    $message['content'],
                    $messageCount
                ]);
            }
        }

        exit;
    }

    private function exportJSON(array $chats): void
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="chats_export.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        foreach ($chats as &$chat) {
            $messages = json_decode($chat['messages'],true);
            $chat['numberOfMessages'] = count($messages); // Added number of messages
        }

        echo wp_json_encode($chats,JSON_PRETTY_PRINT);
        exit;
    }

    private function exportPDF(array $chats): void
    {
        try {
            require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/class-sintacs-Tcpdf.php');

            $pdf = new \SintacsMwaiExportChats\SintacsTCPDFPlugin();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->AddPage();

            foreach ($chats as $chat) {
                $messages = json_decode($chat['messages'],true);
                $chatbotSettings = $this->database->getChatbotSettings($chat['botId']);
                $userMessagesCount = count(array_filter($messages,fn($message) => $message['role'] === 'user'));


                $createdDate = strtotime($chat['created']);
                $createdFormatted = wp_date(get_option('date_format') . ' ' . get_option('time_format'),$createdDate);
                $wochentagKurz = wp_date('D',$createdDate);
                $createdDeutsch = $wochentagKurz . ', ' . $createdFormatted;

                $headerHtml = "<h2>" . __('Chat Details','sintacs-mwai-export-chats') . "</h2>"
                    . "<strong>" . __('ChatID:','sintacs-mwai-export-chats') . "</strong> {$chat['chatId']}<br>"
                    . "<strong>" . __('BotName:','sintacs-mwai-export-chats') . "</strong> {$chatbotSettings['name']}<br>"
                    . "<strong>" . __('Created:','sintacs-mwai-export-chats') . "</strong> {$createdFormatted}<br>"
                    . "<strong>" . __('User Messages:','sintacs-mwai-export-chats') . "</strong> {$userMessagesCount}<br>";
                $pdf->writeHTMLCell(0,0,'','',$headerHtml,0,1,0,true,'',true);

                foreach ($messages as $message) {
                    $rolePrefix = ucfirst($message['role']) . ': ';
                    $pdf->SetTextColor($message['role'] === 'user' ? 0 : 0,0,$message['role'] === 'user' ? 139 : 0);
                    $align = $message['role'] === 'user' ? 'L' : 'R';
                    $pdf->MultiCell(0,10,$rolePrefix . $message['content'],0,$align,false,1);
                    $pdf->Ln(5);
                }

                $pdf->Ln(10);
                $pdf->Line(10,0,200,0);
            }

            $pdf->Output('chat-export.pdf','I');
        } catch (\Exception $e) {
            // Fehler protokollieren
            error_log('PDF-Export-Fehler: ' . $e->getMessage());

            // Benutzerfreundliche Fehlermeldung anzeigen
            wp_die(
                __('There was an error creating the PDF. Please try again later or contact the administrator.','sintacs-mwai-export-chats'),
                __('PDF Export Error','sintacs-mwai-export-chats'),
                ['response' => 500]
            );
        }
    }

}