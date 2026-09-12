<?php

test('lists every status with its label, in board order', function () {
    $this->getJson(route('api.v1.statuses.index'))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'not_started', 'label' => 'Não iniciada'],
            ['value' => 'in_progress', 'label' => 'Em andamento'],
            ['value' => 'completed', 'label' => 'Concluída'],
            ['value' => 'cancelled', 'label' => 'Cancelada'],
        ]]);
});

test('is readable without a token', function () {
    // The list is the same for everyone and holds no user data, so requiring a
    // token would only stop a client from rendering a status picker before
    // anybody has signed in.
    $this->getJson(route('api.v1.statuses.index'))->assertOk();
});
