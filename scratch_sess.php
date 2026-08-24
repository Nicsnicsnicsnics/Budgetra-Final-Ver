<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth; use Illuminate\Cookie\CookieValuePrefix;
$u = App\Models\User::whereHas('userProfile')->where(fn($q)=>$q->whereNull('role')->orWhere('role','!=','admin'))->first();
$s=app('session.store'); $s->start(); Auth::login($u); $s->save();
$n=config('session.cookie'); $e=app('encrypter');
echo $e->encrypt(CookieValuePrefix::create($n,$e->getKey()).$s->getId(), false), "\n";
