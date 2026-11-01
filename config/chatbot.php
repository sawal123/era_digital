<?php

return [
    'enabled' => env('POS_CHATBOT_ENABLED', true),
    'timeout' => 25,
    'max_message_length' => 1000,
    'max_output_tokens' => 1000,
    'history_pairs' => 5,
    'requests_per_minute' => (int) env('POS_CHATBOT_REQUESTS_PER_MINUTE', 10),
    'requests_per_day' => (int) env('POS_CHATBOT_REQUESTS_PER_DAY', 100),
];
