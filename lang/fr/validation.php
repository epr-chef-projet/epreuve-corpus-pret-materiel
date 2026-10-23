<?php

declare(strict_types=1);

return [
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit contenir du texte.',
    'max' => ['string' => 'Le champ :attribute ne doit pas dépasser :max caractères.'],
    'integer' => 'Le champ :attribute doit être un entier.',
    'between' => ['numeric' => 'Le champ :attribute doit être compris entre :min et :max.'],
    'min' => ['numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.'],
    'uuid' => 'Le format du champ :attribute est invalide.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'not_regex' => 'Le champ :attribute contient un caractère interdit.',
    'in' => 'La valeur du champ :attribute est invalide.',
    'attributes' => ['login' => 'identifiant', 'password' => 'mot de passe', 'name' => 'nom', 'category' => 'catégorie', 'description' => 'description', 'totalQuantity' => 'quantité initiale', 'expectedVersion' => 'version attendue', 'operationKey' => 'clé d’enregistrement'],
];
