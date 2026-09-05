<?php

use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'landing'])->name('shop.home');
Route::get('/kategori/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/varukorg', [ShopController::class, 'cart'])->name('shop.cart');
