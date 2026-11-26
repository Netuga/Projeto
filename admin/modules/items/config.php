<?php
return [
    'label' => 'Items',
    'title' => 'Gestão de Items',
    'description' => 'Gere os itens apresentados na wiki.',
    'table' => 'items',
    'columns' => [
        ['name' => 'name', 'label' => 'Nome'], ['name' => 'rarity', 'label' => 'Raridade'],
        ['name' => 'type', 'label' => 'Tipo'], ['name' => 'dmg_bonus', 'label' => 'Bónus de dano'],
    ],
    'fields' => [
        ['name' => 'name', 'label' => 'Nome', 'required' => true], ['name' => 'emoji', 'label' => 'Emoji'],
        ['name' => 'img', 'label' => 'Imagem'], ['name' => 'bg', 'label' => 'Fundo CSS', 'wide' => true],
        ['name' => 'rarity', 'label' => 'Raridade', 'default' => 'rare'], ['name' => 'type', 'label' => 'Tipo'],
        ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea', 'wide' => true],
        ['name' => 'effect', 'label' => 'Efeito', 'wide' => true], ['name' => 'obtain_method', 'label' => 'Como obter', 'wide' => true],
        ['name' => 'dmg_bonus', 'label' => 'Bónus de dano', 'type' => 'number', 'step' => '0.01', 'default' => '0'],
        ['name' => 'spd_bonus', 'label' => 'Bónus de velocidade', 'type' => 'number', 'step' => '0.01'],
    ],
];
