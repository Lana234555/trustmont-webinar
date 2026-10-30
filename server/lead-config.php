<?php
/* Settings for lead.php. This file runs as PHP, so its contents are never shown in a browser. */
return [
  // Uspacy lead processor
  'crm_url'      => 'https://rubicon.tips/uspacy/customers/pro_part_real_estate/leads_processor/leads_processor.php',

  // Telegram: bot token from @BotFather and the chat/group id where leads go (leave empty to skip)
  'tg_token'     => '',
  'tg_chat_id'   => '',

  // Google Sheet: URL of the Apps Script web app (leave empty to skip)
  'sheet_url'    => '',
  'sheet_secret' => '',
];
