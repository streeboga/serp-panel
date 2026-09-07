<?php

use App\Mcp\Servers\SerpPanelServer;
use Laravel\Mcp\Facades\Mcp;

// Тот же Bearer-токен, что и у REST API: Authorization: Bearer <token>.
Mcp::web('/mcp', SerpPanelServer::class)->middleware(['auth:sanctum', 'throttle:60,1']);
