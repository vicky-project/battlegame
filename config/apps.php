<?php

return [

  'id' => 'battle-game',
  'name' => 'Battle Game',
  'description' => 'Pertarungan Battle antar Hero.',
  'icon_emoji' => '⚔️',
  'render_type' => 'iframe',
  'render_config' => [
    'url' => env("APP_URL") . '/apps/battlegames'
  ]
];