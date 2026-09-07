<?php

declare(strict_types=1);

use App\Mcp\Servers\SerpPanelServer;

covers(SerpPanelServer::class);

// Клиент без Accept — типичный curl или сломанный агент. Ответ обязан быть
// 401 в JSON, а не 500 из-за редиректа на несуществующую страницу входа.
it('без токена отвечает 401, даже если клиент не прислал Accept', function () {
    $this->post('/mcp', [], ['Content-Type' => 'application/json'])->assertStatus(401);
    $this->postJson('/mcp', [])->assertStatus(401);
});
