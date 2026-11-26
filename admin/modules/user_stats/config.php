<?php
return [
    'label' => 'User stats', 'title' => 'Estatísticas de utilizadores',
    'description' => 'Consulta as estatísticas guardadas por conta.', 'table' => 'user_stats', 'primary' => 'user_id', 'readonly' => true,
    'columns' => [
        ['name' => 'pvp_rank', 'label' => 'Rank PvP'], ['name' => 'pvp_points', 'label' => 'Pontos PvP'],
        ['name' => 'total_visits', 'label' => 'Visitas'], ['name' => 'favorite_unit', 'label' => 'Unit favorita'],
    ],
];
