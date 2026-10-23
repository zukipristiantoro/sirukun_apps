<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Audit;
class AuthController {
 public function login(){return auth()->check()?redirect('/'):view('login');}
 public function authenticate(Request $r){
  $v=$r->validate(['email'=>'required|email|max:191','password'=>'required|string|max:200']);$key='login:'.hash('sha256',strtolower($v['email']).'|'.$r->ip());
  if(RateLimiter::tooManyAttempts($key,5))return back()->withErrors(['email'=>'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.'])->onlyInput('email');
  if(Auth::attempt(['email'=>$v['email'],'password'=>$v['password'],'aktif'=>true])){RateLimiter::clear($key);$r->session()->regenerate();Audit::record('login','pengguna',auth()->id());return redirect()->intended('/');}
  RateLimiter::hit($key,60);return back()->withErrors(['email'=>'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
 }
 public function logout(Request $r){Audit::record('logout','pengguna',auth()->id());Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
 public function profile(){return view('profile');}
 public function password(Request $r){$v=$r->validate(['current_password'=>'required|current_password','password'=>'required|string|min:6|max:100|confirmed']);$u=$r->user();$u->password_hash=Hash::make($v['password']);$u->save();$r->session()->regenerate();Audit::record('ubah_kata_sandi','pengguna',$u->id);return back()->with('success','Kata sandi berhasil diperbarui.');}
}
