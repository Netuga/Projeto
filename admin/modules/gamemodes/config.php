<?php
return [
    'label' => 'Gamemodes',
    'title' => 'Gestão de Gamemodes',
    'description' => 'Edita a apresentação, informação e recompensas dos modos.',
    'table' => 'gamemodes',
    'columns' => [
        ['name' => 'mode_key', 'label' => 'Tipo'],
        ['name' => 'label', 'label' => 'Nome'],
        ['name' => 'badge', 'label' => 'Badge'],
        ['name' => 'sort_order', 'label' => 'Ordem'],
    ],
    'fields' => [
        ['name' => 'mode_key', 'label' => 'Tipo (story, raid, event...)', 'required' => true],
        ['name' => 'label', 'label' => 'Nome apresentado', 'required' => true],
        ['name' => 'badge', 'label' => 'Badge'],
        ['name' => 'preview_title', 'label' => 'Título da pré-visualização'],
        ['name' => 'preview_text', 'label' => 'Texto da pré-visualização', 'type' => 'textarea', 'wide' => true],
        ['name' => 'gradient', 'label' => 'Gradiente CSS', 'wide' => true],
        ['name' => 'bg_image', 'label' => 'Imagem de fundo', 'wide' => true],
        ['name' => 'meta_json', 'label' => 'Informação (JSON)', 'type' => 'textarea', 'wide' => true],
        ['name' => 'rewards_json', 'label' => 'Recompensas (JSON)', 'type' => 'textarea', 'wide' => true],
        ['name' => 'notes_json', 'label' => 'Notas (JSON)', 'type' => 'textarea', 'wide' => true],
        ['name' => 'sort_order', 'label' => 'Ordem', 'type' => 'number', 'default' => '0'],
    ],
];
