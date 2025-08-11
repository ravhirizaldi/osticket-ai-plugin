<?php
return array(
    'id'          => 'ask:ai',
    'version'     => '1.0.0',
    'ost_version' => '1.17', # Require osTicket v1.17+
    'name'        => 'Ask AI',
    'author'      => 'Ravhi Rizaldi <ravhirzld@gmail.com>',
    'description' => 'Integration with AI to get quick answers from tickets, Currently only supports Gemini AI.',
    'plugin'      => 'askai.php:AskAIPlugin'
);
