<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Sin rutas: la aplicación no usa tokens de API. La antigua GET /api/user
// devolvía el modelo de usuario completo y se eliminó en la auditoría de
// seguridad (fase 04).
