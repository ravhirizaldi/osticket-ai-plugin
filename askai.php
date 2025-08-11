<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once 'config.php';

class AskAIPlugin extends Plugin
{
    var $config_class = 'AskAIPluginConfig';

    function bootstrap()
    {
        $cfg    = $this->getConfig();
        $apiKey = $cfg && $cfg->get('api_key') ? $cfg->get('api_key') : getenv('GEMINI_API_KEY');
        $model  = $cfg && $cfg->get('model')  ? $cfg->get('model')  : 'gemini-2.0-flash';
        $langSetting = $cfg && $cfg->get('language') ? $cfg->get('language') : 'id'; // 'auto'|'id'|'en'|...

        // Mapping from language codes to names
        $languageNames = [
            'id' => 'Indonesian',
            'en' => 'English',
            'ms' => 'Malay',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'zh' => 'Chinese (Simplified)',
            'ar' => 'Arabic',
            'es' => 'Spanish',
        ];

        Signal::connect('ticket.view.more', function ($ticket, &$extras) {
            global $thisstaff;
            if (!$thisstaff || !$thisstaff->isStaff())
                return;

            echo sprintf(
                '<li><a href="#%s" onclick="javascript: $.dialog($(this).attr(\'href\').substr(1), 201); return false;"><i class="icon-comments"></i> %s</a></li>',
                'ajax.php/askai/ticket/' . $ticket->getId() . '/view',
                __('Ask AI')
            );
        });

        Signal::connect('ajax.scp', function ($dispatcher) use ($apiKey, $model, $langSetting, $languageNames) {
            // --- VIEW DIALOG
            $dispatcher->append(
                url_get('^/askai/ticket/(?P<id>\d+)/view$', function ($ticketId) {
                    global $thisstaff;
                    require_once INCLUDE_DIR . 'class.ticket.php';

                    $ticket = Ticket::lookup((int)$ticketId);
                    if (!$ticket) Http::response(404, 'No such ticket');
                    if (!$thisstaff || !$thisstaff->isStaff()) Http::response(403, 'Not allowed');

                    $question = 'This ticket has no content';
                    $thread = $ticket->getThread();
                    if ($thread && method_exists($thread, 'getEntries')) {
                        foreach ($thread->getEntries() as $E) {
                            $type = method_exists($E, 'getType') ? $E->getType() : null;
                            if ($type === 'M' || $type === null) {
                                $body = trim(strip_tags($E->getBody()));
                                $title = $ticket->getSubject();
                                if ($body !== '') {
                                    $question = $title . ' - ' . $body;
                                    break;
                                }
                            }
                        }
                    }
                    if ($question === 'This ticket has no content') {
                        // Fallback to subject if no body found
                        $subject = $ticket->getSubject();
                        if ($subject) $question = $subject;
                    }

                    include 'templates/ticket-askai.tmpl.php';
                })
            );

            // --- GENERATE
            $dispatcher->append(
                url_post('^/askai/generate$', function () use ($apiKey, $model, $langSetting, $languageNames) {
                    if (!$apiKey) {
                        Http::response(500, json_encode(['error' => 'API key not set']), 'application/json');
                    }

                    $input = file_get_contents('php://input');
                    $data  = json_decode($input, true);
                    $question = isset($data['question']) ? $data['question'] : '';
                    $ticketId = isset($data['ticketId']) ? (int)$data['ticketId'] : 0;

                    // Sanitize and normalize the question
                    $question = preg_replace('/(^>.*$)/m', '', $question);          // remove quotes
                    $question = preg_replace('/-{2,}.+$/s', '', $question);          // remove hr-ish
                    $question = preg_replace('/\s+/', ' ', $question);               // normalize

                    // Determine language setting
                    $language = $langSetting ?: 'id';
                    if ($language === 'auto' && $ticketId) {
                        require_once INCLUDE_DIR . 'class.ticket.php';
                        if ($ticket = Ticket::lookup($ticketId)) {
                            $ticketLang = null;
                            if (method_exists($ticket, 'getLocale') && $ticket->getLocale()) {
                                $ticketLang = substr($ticket->getLocale(), 0, 2);
                            } elseif (method_exists($ticket, 'getLanguage') && $ticket->getLanguage()) {
                                $ticketLang = substr($ticket->getLanguage(), 0, 2);
                            }
                            if ($ticketLang) $language = $ticketLang;
                        }
                        if ($language === 'auto') $language = 'id'; // fallback
                    }
                    $languageName = isset($languageNames[$language]) ? $languageNames[$language] : 'Indonesian';

                    // Prompt dinamis sesuai bahasa
                    $prompt = <<<EOT
Task: Output ONLY a JSON array of step-by-step fix actions for the following issue, written in {$languageName}.
Rules:
- Output must be ONLY valid JSON, no extra text, no greetings/roleplay/explanations/summary/closing.
- Each step = exactly one actionable command or instruction, imperative mood.
- If commands are needed (Windows, Linux, etc.), include the exact command.
- No numbering/bullets/formatting outside JSON.

Issue:
"$question"
EOT;

                    // System instruction to ensure AI understands their role
                    $systemInstruction = "You are a troubleshooting assistant. Always return a pure JSON array of strings containing concise, actionable steps. Write responses in {$languageName}.";

                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

                    $payload = [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'system_instruction' => [
                            'parts' => [
                                ['text' => $systemInstruction]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => [
                                'type' => 'ARRAY',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'steps' => [
                                            'type' => 'ARRAY',
                                            'items' => [
                                                'type' => 'STRING'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    $response = curl_exec($ch);
                    $err = curl_error($ch);
                    curl_close($ch);

                    if ($err) {
                        Http::response(500, json_encode(['error' => $err]), 'application/json');
                    }

                    $decodedResponse = json_decode($response, true);
                    if (
                        json_last_error() === JSON_ERROR_NONE &&
                        isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])
                    ) {
                        $geminiText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];

                        // Harusnya sudah JSON array of strings
                        $parsed = json_decode($geminiText, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                            Http::response(200, json_encode($parsed), 'application/json');
                        } else {
                            Http::response(500, json_encode(['error' => 'Failed to parse AI JSON output']), 'application/json');
                        }
                    } else {
                        Http::response(500, json_encode(['error' => 'Invalid or incomplete response from AI service']), 'application/json');
                    }
                })
            );
        });
    }
}
